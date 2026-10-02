<?php

namespace BAR\Fail2banTools\Service;

use XF\App;
use XF\Service\AbstractService;

/**
 * Talks to the host fail2ban via a root-only wrapper (sudo).
 * Wrapper path: /usr/local/sbin/bareefers-fail2ban-acl
 */
class Fail2banClient extends AbstractService
{
	public const JAILS = [
		'bareefers-nginx-limit',
		'bareefers-nginx-whatsnew',
		'bareefers-nginx-botua',
		'bareefers-recidive',
	];

	protected string $wrapper = '/usr/local/sbin/bareefers-fail2ban-acl';
	protected string $sudo = '/usr/bin/sudo';

	public function __construct(App $app)
	{
		parent::__construct($app);

		$configWrapper = $app->config()['barFail2banWrapper'] ?? null;
		if (is_string($configWrapper) && $configWrapper !== '')
		{
			$this->wrapper = $configWrapper;
		}
	}

	public function isAvailable(): bool
	{
		return is_executable($this->wrapper) && is_executable($this->sudo);
	}

	public function getUnavailableReason(): string
	{
		if (!is_executable($this->wrapper))
		{
			return 'Helper missing or not executable: ' . $this->wrapper
				. ' (install ops/fail2ban/bareefers-fail2ban-acl.sh and sudoers).';
		}
		if (!is_executable($this->sudo))
		{
			return 'sudo is not available to the web user.';
		}

		return 'fail2ban helper is not available.';
	}

	/**
	 * @return array{ok: bool, error?: string, jails: array<string, array{banned: bool, currently_failed?: int}>}
	 */
	public function lookupIp(string $ip): array
	{
		$ip = $this->normalizeIp($ip);
		if ($ip === null)
		{
			return ['ok' => false, 'error' => 'Invalid IP address.', 'jails' => []];
		}

		$result = $this->run(['lookup', $ip]);
		if (!$result['ok'])
		{
			return ['ok' => false, 'error' => $result['error'], 'jails' => []];
		}

		$jails = [];
		foreach (self::JAILS as $jail)
		{
			$jails[$jail] = ['banned' => false];
		}

		foreach (preg_split('/\r\n|\r|\n/', $result['stdout']) as $line)
		{
			$line = trim($line);
			if ($line === '' || !str_contains($line, "\t"))
			{
				continue;
			}
			[$jail, $state] = explode("\t", $line, 2);
			if (!isset($jails[$jail]))
			{
				continue;
			}
			$jails[$jail]['banned'] = ($state === 'banned');
		}

		return ['ok' => true, 'jails' => $jails, 'ip' => $ip];
	}

	/**
	 * @return array{ok: bool, error?: string, unbanned: string[], messages: string[]}
	 */
	public function unbanIp(string $ip): array
	{
		$ip = $this->normalizeIp($ip);
		if ($ip === null)
		{
			return ['ok' => false, 'error' => 'Invalid IP address.', 'unbanned' => [], 'messages' => []];
		}

		$result = $this->run(['unban', $ip]);
		if (!$result['ok'])
		{
			return ['ok' => false, 'error' => $result['error'], 'unbanned' => [], 'messages' => []];
		}

		$unbanned = [];
		$messages = [];
		foreach (preg_split('/\r\n|\r|\n/', $result['stdout']) as $line)
		{
			$line = trim($line);
			if ($line === '')
			{
				continue;
			}
			$messages[] = $line;
			if (str_starts_with($line, 'UNBANNED '))
			{
				$parts = explode(' ', $line, 3);
				if (isset($parts[1]))
				{
					$unbanned[] = $parts[1];
				}
			}
		}

		return ['ok' => true, 'unbanned' => $unbanned, 'messages' => $messages, 'ip' => $ip];
	}

	public function normalizeIp(string $ip): ?string
	{
		$ip = trim($ip);
		if ($ip === '')
		{
			return null;
		}
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6))
		{
			return $ip;
		}

		return null;
	}

	/**
	 * @param list<string> $args
	 * @return array{ok: bool, stdout: string, stderr: string, error?: string, code: int}
	 */
	protected function run(array $args): array
	{
		if (!$this->isAvailable())
		{
			return [
				'ok' => false,
				'stdout' => '',
				'stderr' => '',
				'error' => $this->getUnavailableReason(),
				'code' => 127,
			];
		}

		$cmd = array_merge([$this->sudo, '-n', $this->wrapper], $args);
		$descriptors = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];
		$proc = proc_open($cmd, $descriptors, $pipes, null, null, ['bypass_shell' => true]);
		if (!is_resource($proc))
		{
			return [
				'ok' => false,
				'stdout' => '',
				'stderr' => '',
				'error' => 'Could not start fail2ban helper.',
				'code' => 1,
			];
		}

		fclose($pipes[0]);
		$stdout = stream_get_contents($pipes[1]) ?: '';
		$stderr = stream_get_contents($pipes[2]) ?: '';
		fclose($pipes[1]);
		fclose($pipes[2]);
		$code = proc_close($proc);

		if ($code !== 0)
		{
			$err = trim($stderr !== '' ? $stderr : $stdout);
			if ($err === '')
			{
				$err = 'fail2ban helper exited with code ' . $code;
			}

			return [
				'ok' => false,
				'stdout' => $stdout,
				'stderr' => $stderr,
				'error' => $err,
				'code' => $code,
			];
		}

		return [
			'ok' => true,
			'stdout' => $stdout,
			'stderr' => $stderr,
			'code' => $code,
		];
	}
}
