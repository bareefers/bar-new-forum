<?php

namespace BAR\Fail2banTools\Admin\Controller;

use BAR\Fail2banTools\Service\Fail2banClient;
use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;
use XF\Util\Ip as IpUtil;

class Fail2ban extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('ban');
	}

	public function actionIndex()
	{
		$client = $this->service('BAR\Fail2banTools:Fail2banClient');

		return $this->view('BAR\Fail2banTools:Fail2ban\Index', 'bar_f2b_index', [
			'helperAvailable' => $client->isAvailable(),
			'helperReason' => $client->isAvailable() ? '' : $client->getUnavailableReason(),
			'jails' => Fail2banClient::JAILS,
			'query' => '',
			'results' => null,
			'notice' => $this->pullNotice(),
		]);
	}

	public function actionLookup()
	{
		$this->assertPostOnly();

		$query = trim($this->filter('query', 'str'));
		if ($query === '')
		{
			return $this->error(\XF::phrase('bar_f2b_enter_username_or_ip'));
		}

		$client = $this->service('BAR\Fail2banTools:Fail2banClient');
		$user = null;
		$ips = [];

		if ($client->normalizeIp($query) !== null)
		{
			$ips[] = [
				'ip' => $client->normalizeIp($query),
				'last_seen' => null,
				'actions' => [],
			];
		}
		else
		{
			$user = $this->finder('XF:User')
				->where('username', $query)
				->fetchOne();

			if (!$user)
			{
				return $this->error(\XF::phrase('requested_user_not_found'));
			}

			$ips = $this->findRecentIpsForUser($user->user_id);
			if (!$ips)
			{
				return $this->error(\XF::phrase('bar_f2b_no_recent_ips'));
			}
		}

		$results = [];
		foreach ($ips as $row)
		{
			$lookup = $client->isAvailable()
				? $client->lookupIp($row['ip'])
				: ['ok' => false, 'error' => $client->getUnavailableReason(), 'jails' => []];

			$bannedJails = [];
			if (!empty($lookup['ok']))
			{
				foreach ($lookup['jails'] as $jail => $info)
				{
					if (!empty($info['banned']))
					{
						$bannedJails[] = $jail;
					}
				}
			}

			$results[] = [
				'ip' => $row['ip'],
				'last_seen' => $row['last_seen'],
				'actions' => $row['actions'],
				'lookup_ok' => !empty($lookup['ok']),
				'lookup_error' => $lookup['error'] ?? '',
				'jails' => $lookup['jails'] ?? [],
				'banned_jails' => $bannedJails,
				'is_banned' => $bannedJails !== [],
			];
		}

		return $this->view('BAR\Fail2banTools:Fail2ban\Index', 'bar_f2b_index', [
			'helperAvailable' => $client->isAvailable(),
			'helperReason' => $client->isAvailable() ? '' : $client->getUnavailableReason(),
			'jails' => Fail2banClient::JAILS,
			'query' => $query,
			'user' => $user,
			'results' => $results,
			'notice' => $this->pullNotice(),
		]);
	}

	public function actionUnban()
	{
		$this->assertPostOnly();

		$ip = $this->filter('ip', 'str');
		$client = $this->service('BAR\Fail2banTools:Fail2banClient');

		if (!$client->isAvailable())
		{
			$this->setNotice('error', \XF::phrase('bar_f2b_unban_failed', [
				'ip' => $ip,
				'reason' => $client->getUnavailableReason(),
			]));

			return $this->redirect($this->buildLink('bar-fail2ban'));
		}

		$result = $client->unbanIp($ip);
		if (!$result['ok'])
		{
			$this->setNotice('error', \XF::phrase('bar_f2b_unban_failed', [
				'ip' => $client->normalizeIp($ip) ?: $ip,
				'reason' => $result['error'] ?? 'Unknown error',
			]));

			return $this->redirect($this->buildLink('bar-fail2ban'));
		}

		$unbanned = $result['unbanned'] ?? [];
		$failed = [];
		foreach ($result['messages'] ?? [] as $line)
		{
			if (str_starts_with($line, 'FAILED '))
			{
				$parts = explode(' ', $line, 3);
				if (isset($parts[1]))
				{
					$failed[] = $parts[1];
				}
			}
		}

		$visitor = \XF::visitor();
		error_log(sprintf(
			'[bar-fail2ban] admin=%s(%d) unban=%s jails=%s failed=%s',
			$visitor->username,
			$visitor->user_id,
			$result['ip'] ?? $ip,
			implode(',', $unbanned),
			implode(',', $failed)
		));

		$displayIp = $result['ip'] ?? $ip;

		if ($unbanned && !$failed)
		{
			$this->setNotice('success', \XF::phrase('bar_f2b_unban_success', [
				'ip' => $displayIp,
				'jails' => implode(', ', $unbanned),
			]));
		}
		else if ($unbanned && $failed)
		{
			$this->setNotice('warning', \XF::phrase('bar_f2b_unban_partial', [
				'ip' => $displayIp,
				'ok' => implode(', ', $unbanned),
				'fail' => implode(', ', $failed),
			]));
		}
		else
		{
			$this->setNotice('error', \XF::phrase('bar_f2b_unban_not_banned', [
				'ip' => $displayIp,
			]));
		}

		return $this->redirect($this->buildLink('bar-fail2ban'));
	}

	/**
	 * @param string $type success|error|warning
	 * @param \XF\Phrase|string $message
	 */
	protected function setNotice(string $type, $message): void
	{
		$this->session()->set('barF2bNotice', [
			'type' => $type,
			'message' => (string) $message,
		]);
	}

	/**
	 * @return array{type: string, message: string}|null
	 */
	protected function pullNotice(): ?array
	{
		$session = $this->session();
		$notice = $session->get('barF2bNotice');
		if (is_array($notice) && !empty($notice['message']))
		{
			$session->remove('barF2bNotice');

			return [
				'type' => $notice['type'] ?? 'success',
				'message' => (string) $notice['message'],
				'class' => match ($notice['type'] ?? 'success')
				{
					'error' => 'blockMessage--error',
					'warning' => 'blockMessage--important',
					default => 'blockMessage--success',
				},
			];
		}

		return null;
	}

	/**
	 * @return list<array{ip: string, last_seen: int|null, actions: list<string>}>
	 */
	protected function findRecentIpsForUser(int $userId): array
	{
		$db = $this->app()->db();
		$rows = $db->fetchAll("
			SELECT ip, MAX(log_date) AS last_seen,
				GROUP_CONCAT(DISTINCT CONCAT(content_type, ':', action) ORDER BY log_date DESC SEPARATOR ', ') AS actions
			FROM xf_ip
			WHERE user_id = ?
			GROUP BY ip
			ORDER BY last_seen DESC
			LIMIT 15
		", $userId);

		$out = [];
		foreach ($rows as $row)
		{
			$ip = IpUtil::convertIpBinaryToString($row['ip']);
			if ($ip === false || $ip === '')
			{
				continue;
			}
			$out[] = [
				'ip' => $ip,
				'last_seen' => (int) $row['last_seen'],
				'actions' => array_values(array_filter(array_map('trim', explode(',', (string) $row['actions'])))),
			];
		}

		return $out;
	}
}
