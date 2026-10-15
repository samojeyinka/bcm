<?php

function esc_html($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
function esc_url($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
function absint($v){ return abs((int)$v); }
function wp_unslash($v){ return is_string($v) ? stripslashes($v) : $v; }
function sanitize_text_field($s){ return trim(strip_tags((string)$s)); }
function apply_filters($tag, $v){ return $v; }
function wp_reset_postdata(){ $GLOBALS['__cur'] = null; }
function get_the_title(){ return $GLOBALS['__cur']['title']; }
function get_the_content(){ return $GLOBALS['__cur']['content']; }

class WP_Query {
	public int $max_num_pages = 0; private array $posts = []; private int $i = 0;
	public function __construct(array $a) {
		static $db = null;
		if ($db === null) { 
			for ($n = 1; $n <= 25; $n++) {
				$c = "<p>Body of post $n</p>";
				if ($n === 5)  $c = "<p>Intro [ad] middle [ad] end</p>";
				if ($n === 12) $c = "<p>Top</p><p>[ad]</p><p>Bottom [ad]</p>";
				$db[] = ['title' => "Post $n", 'content' => $c];
			}
		}
		$s = $a['s'] ?? '';
		$m = array_values(array_filter($db, fn($p) => $s === '' || stripos($p['title'].$p['content'], $s) !== false));
		$per = $a['posts_per_page'];
		$this->max_num_pages = (int) ceil(count($m) / $per);
		$this->posts = array_slice($m, (($a['paged'] ?? 1) - 1) * $per, $per);
	}
	public function have_posts(): bool { return $this->i < count($this->posts); }
	public function the_post(): void { $GLOBALS['__cur'] = $this->posts[$this->i++]; }
}
