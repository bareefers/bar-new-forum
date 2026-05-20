<?php
/**
 * extra.less: sticky nav top offset (guest/member flush, staff under bar)
 *
 * CSS cannot read “logged in” or “admin” — only what appears in the HTML.
 * XenForo does not put guest/admin classes on <body> by default. The reliable
 * split is: pages with a staff tools row include .p-staffBar; guests and normal
 * members do not. We use :has(.p-staffBar) (with a class fallback) so rules match
 * that logic without <xf:if> (merged CSS is not per-user).
 *
 * Guests: .p-navSticky stays flush (top: 0, no padding) so header/wave art can meet
 * the viewport top. Inset logo/links with padding on .p-nav-inner only — not on .p-nav
 * (padding there + background-image shifts how the gradient reads vs the wave header).
 */
$root = $argv[1] ?? '/var/www/bareefers.org/forum';
$config = [];
require $root . '/src/config.php';
$host = $config['db']['host'] ?? '127.0.0.1';
if ($host === 'localhost') {
    $host = '127.0.0.1';
}
$dsn = 'mysql:host=' . $host . ';port=' . ($config['db']['port'] ?? 3306) . ';dbname=' . $config['db']['dbname'] . ';charset=utf8mb4';
$pdo = new PDO($dsn, $config['db']['username'], $config['db']['password']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$t = $pdo->query(
    "SELECT template FROM xf_template WHERE style_id=16 AND title='extra.less' AND type='public'"
)->fetchColumn();
if ($t === false) {
    fwrite(STDERR, "extra.less not found\n");
    exit(1);
}

$newBlock = <<<'LESS'
@supports (position: sticky) or (position: -webkit-sticky) {
	/*
	 * No .p-staffBar on guests / regular members → nav sticks at viewport top.
	 * Staff (mod/admin) pages include .p-staffBar → nav sits under sticky bar (~35px).
	 * :has() raises specificity so theme .p-navSticky rules cannot leave a false gap.
	 */
	@supports selector(:has(*)) {
		body:not(:has(.p-staffBar)) .p-navSticky.p-navSticky--primary {
			position: -webkit-sticky;
			position: sticky;
			top: 0 !important;
		}
		body:has(.p-staffBar) .p-navSticky.p-navSticky--primary {
			position: -webkit-sticky;
			position: sticky;
			top: 35px !important;
		}
	}
	/* No :has(): rely on Doh class on nav when sticky staff tools are on */
	.p-navSticky:not(.p-staffSticky) {
		position: -webkit-sticky;
		position: sticky;
		top: 0 !important;
	}
	.p-navSticky.p-staffSticky {
		position: -webkit-sticky;
		position: sticky;
		top: 35px !important;
	}
}
LESS;

/**
 * Replace the outer @supports (position: sticky) … block including nested { }.
 */
function xf_replace_sticky_supports_block(string $template, string $replacement): string
{
    $needle = '@supports (position: sticky) or (position: -webkit-sticky)';
    $pos = strpos($template, $needle);
    if ($pos === false) {
        fwrite(STDERR, "No @supports sticky block found; append at end.\n");

        return rtrim($template) . "\n\n" . rtrim($replacement) . "\n";
    }
    $open = strpos($template, '{', $pos);
    if ($open === false) {
        return rtrim($template) . "\n\n" . rtrim($replacement) . "\n";
    }
    $depth = 0;
    $len = strlen($template);
    for ($i = $open; $i < $len; $i++) {
        $c = $template[$i];
        if ($c === '{') {
            $depth++;
        } elseif ($c === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($template, 0, $pos) . rtrim($replacement) . substr($template, $i + 1);
            }
        }
    }

    return rtrim($template) . "\n\n" . rtrim($replacement) . "\n";
}

$t2 = xf_replace_sticky_supports_block($t, $newBlock);

// Strip legacy padding on the sticky wrapper (pushes whole header down / white gap)
$t2 = preg_replace(
    '/(body:not\(:has\(\.p-staffBar\)\)\s+\.p-navSticky\.p-navSticky--primary\s*\{[^}]*)\s*padding-top:\s*10px\s*!important;\s*/s',
    '$1',
    $t2
);
$t2 = preg_replace(
    '/(\.p-navSticky:not\(\.p-staffSticky\)\s*\{[^}]*)\s*padding-top:\s*10px\s*!important;\s*/s',
    '$1',
    $t2
);

$navInsetStart = "\n\n/* BAR: guest nav inset */\n";
$navInsetEnd = "\n/* BAR: guest nav inset end */\n";
// Gradient on the sticky shell + section row only; padding on .p-nav-inner so the bar
// stays visually flush with the wave while logo/links move down.
// Use @xf-* here. Do NOT paste compiled CSS like hsla(var(--xf-…)) — XenForo LESS will throw.
$barGradNav = 'linear-gradient(354deg, @xf-paletteColor1 0%, @xf-paletteAccent1 100%)';
$navInset = $navInsetStart . <<<LESS
@supports selector(:has(*)) {
	body:not(:has(.p-staffBar)) .p-navSticky.p-navSticky--primary {
		background-image: {$barGradNav} !important;
		background-repeat: no-repeat;
	}
	body:not(:has(.p-staffBar)) .p-sectionLinks {
		background-image: {$barGradNav} !important;
		background-repeat: no-repeat;
	}
	body:not(:has(.p-staffBar)) .p-navSticky.p-navSticky--primary .p-nav .p-nav-inner {
		padding-top: 10px !important;
	}
}
.p-navSticky.p-navSticky--primary:not(.p-staffSticky) {
	background-image: {$barGradNav} !important;
	background-repeat: no-repeat;
}
.p-navSticky.p-navSticky--primary:not(.p-staffSticky) + .p-sectionLinks {
	background-image: {$barGradNav} !important;
	background-repeat: no-repeat;
}
.p-navSticky.p-navSticky--primary:not(.p-staffSticky) .p-nav .p-nav-inner {
	padding-top: 10px !important;
}
LESS;
$navInset .= $navInsetEnd;

// Strip every prior inset block (reruns can leave "}/* BAR…" with no newline — old pattern missed it → duplicate LESS → compile fails).
$t2 = preg_replace(
    '/\/\* BAR: guest nav inset \*\/[\s\S]*?\/\* BAR: guest nav inset end \*\//',
    '',
    $t2
);
$t2 = rtrim($t2) . $navInset;

// Remove legacy wave block (single comment, no end marker) from earlier script versions
$t2 = preg_replace(
    '/\n\n\/\* BAR: guest wave overlap — pull[^\n]*\*\/\s*@supports selector\(:has\(\*\)\) \{\s*body:not\(:has\(\.p-staffBar\)\) \.p-proxy \{\s*margin-top:[^}]+\}\s*\}\s*/s',
    '',
    $t2
);

$waveGuestStart = "\n\n/* BAR: guest wave overlap start */\n";
$waveGuestEnd = "\n/* BAR: guest wave overlap end */\n";
$waveGuest = $waveGuestStart . <<<'LESS'
@supports selector(:has(*)) {
	body:not(:has(.p-staffBar)) .p-proxy {
		margin-top: -140px !important;
	}
}
LESS;
$waveGuest .= $waveGuestEnd;
$t2 = preg_replace(
    '/\/\* BAR: guest wave overlap start \*\/[\s\S]*?\/\* BAR: guest wave overlap end \*\//',
    '',
    $t2
);
$t2 = rtrim($t2) . $waveGuest;

// Also fix stray top: 20px on .p-navSticky if any
$t2 = preg_replace(
    '/(\.p-navSticky[^{]*\{[^}]*top:\s*)20px/s',
    '${1}0',
    $t2
);

$st = $pdo->prepare(
    'UPDATE xf_template SET template = ?, last_edit_date = ? WHERE style_id=16 AND title=\'extra.less\' AND type=\'public\''
);
$st->execute([$t2, time()]);
echo "OK: updated extra.less sticky nav block\n";
