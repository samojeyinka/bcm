<?php
// ============== run: php tests/run-tests.php ==============
// Every PHP warning or notice is turned into a failure below,
// so a quiet notice still breaks the run.
error_reporting(E_ALL);
set_error_handler(function($no,$str){ throw new ErrorException($str); });
require __DIR__ . '/wp-stubs.php';

function render(array $get): string {
	$_GET = $get; ob_start();
	include __DIR__ . '/../search-results.php';
	return ob_get_clean();
}
$fails = 0;
function check(string $name, bool $ok, string $detail = ''): void {
	global $fails; if (!$ok) $fails++;
	printf("[%s] %s%s\n", $ok ? 'PASS' : 'FAIL', $name, $ok ? '' : "  -> $detail");
}
$posts = fn(string $h) => preg_match_all('#<h2>#', $h);

// ============== no s, no page: sensible defaults ==============
$h = render([]);
check('missing params: "Page 1 of 3"', str_contains($h, 'Page 1 of 3'));
check('missing params: 10 posts', $posts($h) === 10);
check('missing params: no "Search for"', !str_contains($h, 'Search for'));
check('missing params: no Previous, has Next', !str_contains($h, 'Previous') && str_contains($h, 'rel="next"'));

// ============== page 2 shows different posts, last page has no Next ==============
$h = render(['page' => '2']);
check('page 2: "Page 2 of 3"', str_contains($h, 'Page 2 of 3'));
check('page 2: posts 11-20 only', str_contains($h, 'Post 11') && str_contains($h, 'Post 20') && !str_contains($h, 'Post 21') && !str_contains($h, 'Post 10<'));
check('page 2: Previous + Next links', str_contains($h, 'rel="prev"') && str_contains($h, 'rel="next"'));
$h = render(['page' => '3']);
check('page 3: 5 posts, no Next', $posts($h) === 5 && !str_contains($h, 'rel="next"'));

// ============== junk in ?page= falls back to page 1 instead of warning ==============
foreach (['abc' => 'abc', '-4' => '-4', '0' => '0', 'array' => ['x']] as $label => $v) {
	$h = render(['page' => $v]);
	check("bad page ($label) -> valid page, no warning", preg_match('#Page \d+ of 3#', $h) === 1);
}
$h = render(['page' => '99']);
check('page 99: friendly empty message, no fatal', str_contains($h, 'No posts found on this page'));

// ============== a search that matches nothing still renders a page ==============
$h = render(['s' => 'zzzz-nothing']);
check('no results: message shown', str_contains($h, 'No results found for'));
check('no results: "Page 1 of 1", zero posts', str_contains($h, 'Page 1 of 1') && $posts($h) === 0);

// ============== real searches: escaping, pagination, odd types ==============
$h = render(['s' => 'Post 2']);   
check('search "Post 2": 7 posts, one page', $posts($h) === 7 && str_contains($h, 'Page 1 of 1'));
$h = render(['s' => 'Post', 'page' => '2']);
check('pagination links keep the search term', str_contains($h, 's=Post&amp;page=3'));
$h = render(['s' => '<script>alert(1)</script>']);
check('XSS in s is not echoed raw', !str_contains($h, '<script>'));
$h = render(['s' => ['a']]);
check('array-valued s ignored without warning', str_contains($h, 'Page 1 of 3'));

// ============== [ad] markers become numbered ad divs ==============
$h = render(['page' => '1']);   
preg_match_all('#<div class="ad ad-(\d+)" data-ad-slot="\d+"></div>#', $h, $m);
check('page 1 (post 5): 2 ad divs numbered 1,2', $m[1] === ['1', '2'], json_encode($m[1]));
check('page 1: no literal [ad] left', !str_contains($h, '[ad]'));
$h = render(['page' => '2']);   
preg_match_all('#class="ad ad-(\d+)"#', $h, $m);
check('page 2 (post 12): 2 ads, none left as text', $m[1] === ['1', '2'] && !str_contains($h, '[ad]'));
check('ad-only paragraph is not wrapped in <p>', !str_contains($h, '<p><div'));

// ============== info, not a pass: what the ORIGINAL #[ad]# regex did to a sample string ==============
echo 'original regex #[ad]# on "a [ad] d": ' . preg_replace_callback('#[ad]#', fn($m) => 'X', 'a [ad] d') . "\n";

echo $fails ? "\n$fails FAILED\n" : "\nAll checks passed\n";
exit($fails ? 1 : 0);
