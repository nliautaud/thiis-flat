<?php
ini_set('error_reporting', E_ALL);
define('ROOT_DIR', realpath(dirname(__FILE__)) .'/');
define('ROOT_URL', 'http://' . $_SERVER['SERVER_NAME'] . dirname($_SERVER['SCRIPT_NAME']) . '/');
define('CONTENT_DIR', ROOT_DIR .'content/');
define('THEMES_DIR', ROOT_DIR .'themes/');
define('ADMIN_DIR', ROOT_DIR .'admin/');
define('CONFIG', ROOT_DIR .'config.php');
define('HAS_REWRITE', file_exists(ROOT_DIR .'.htaccess'));
define('HAS_ADMIN', file_exists(ADMIN_DIR .'admin.php'));

include CONFIG;

/*
 * There is thiis below.
 *
 * Thiis is a minimalist-evolving CMS made by Nicolas Liautaud. http://nliautaud.fr/thiis
 * Thiis is provided 'as is' without warranty of any kind, including working. If you found
 * a bug or you have interrogations, ideas or needs about thiis, feel free to contact me.
 *
 * Thiis software is licensed under CC-BY 3.0. http://creativecommons.org/licenses/by/3.0
 * You can use, share and modify thiis for any purpose, just credit its author or website.
 * Data icons are generated from Mark James mini set. http://famfamfam.com/lab/icons/mini
 *
 * @package Thiis
 * @author Nicolas Liautaud <nicolas.liautaud@gmail.com>
 */
class Thiis
{
	var $version = 1364667604;
	var $user;
	var $infos = '';
	var $pages;
	var $config;

	var $footnotes = array();

	/**
	 * CONSTRUCTOR
	 *
	 * @param array $config The website data.
	 */
	function Thiis($config)
	{
		$this->config = $config;

		// GET page path
		$path = $this->format_path(key($_GET));
		if( !$path ) $path = 'index';
		define('PAGE_PATH', $path);

		// POST format
		if(get_magic_quotes_gpc()) {
			$this->post = md_function('stripcslashes', $_POST, 'string');
		} else	$this->post = $_POST;

		// SESSION
		session_start();
		$this->check_login();

		// special outputs
		header('thiis-version: ' . $this->version);
		if( PAGE_PATH == 'rss' && $this->config('setting/rss') ) {
			exit($this->rss());
		}
		if( PAGE_PATH == 'download' && !$this->config('setting/allow_download')) {
			header('Content-disposition: attachment; filename=thiis_'.$this->version.'.php');
			header('Content-type: text/php; charset=utf-8');
			exit(file_get_contents(__FILE__));
		}

		// OUTPUT
		exit($this->output());
	}

	public function output()
	{
		// use the template file matching the page path
		$template_path = THEMES_DIR.PAGE_PATH.'.html';
		if(!file_exists($template_path)) {
			// or the first file in the first existing path
			$existing_path = $this->first_realpath(THEMES_DIR, PAGE_PATH);
			$tpl_list = $this->list_files(THEMES_DIR . $existing_path, '*.html');
			$template_path = reset($tpl_list);
		}
		$content = file_get_contents($template_path);
		$content = $this->parse($content, false, true, 'template "'.$template_path.'"');
		return $content;
	}

	/* GETTERS */


	function format_path( $path )
	{
		$path = explode('/', $path);
		$path = array_filter($path);
		$path = implode('/', $path);
		return $path;
	}
	function first_realpath( $root, $path )
	{
		chdir($root);
		// go up to parents until the path exists
		while( $path && !realpath($path)) {
			$path = dirname($path);
			if($path == '.') return '';
		}
		return $this->format_path($path);
	}

	function list_files( $path, $filter = null )
	{
		if($filter)
			$files = glob($path . $filter, GLOB_BRACE);
		else
			$files = scandir($path);

		$files = array_diff($files, array('.', '..'));

		return $files;
	}

	function config( $path = '', $value = null )
	{
		$path = explode('/', $path);
		$path = array_filter($path);
		$cur =& $this->config;
	    foreach($path as $p) {
	        if(!isset($cur[$p])) {
	            if($value === null) return null;
	            $cur[$p] = array();
	        }
	        $cur =& $cur[$p];
	    }
	    if( $value !== null ) {
	    	$cur = $value;
	    	return;
	    }
		if( !is_array($cur) ) return $cur;
		return string_to_value($cur);
	}

	function config_find( $key, $val = null, $path = '' )
	{
		$arr = $this->config($path);
		if($path) $path .= '/';
		$results = array();
		foreach($arr as $_key => $_val)
		{
			if( is_array($_val) ) {
				$results = array_merge(
					$results,
					$this->config_find($key, $val, $path.$_key)
				);
			}
			if( ($key === null || $key === $_key )
			 && ($val === null || $val === $_val )) {
				$results[] = $path.$key;
			}
		}
		return $results;
	}


	/* PARSER */

	/**
	 * Find and render any thiis markup.
	 *
	 * @param  string  $str The string to parse.
	 * @param  boolean $structure Parse headers, line breaks and paragraphs ? Default : false.
	 * @param  boolean $php Execute php code ? If false, and by default delete php.
	 * @param  array   $info Information texts about what is parsed, for PHP errors.
	 * @return string  The parsed string.
	 */
	function parse($str, $structure = false, $php = true, $info = null)
	{
		if(!is_string($str)) return false;

		// render or delete php
		if($php && strpos($str, '<?php') !== false) {
			try { $str = $this->php_render($str, $info); }
			catch(Exception $phperror) {}
		}
		if(!$php || isset($phperror)) $str = preg_replace('`<\?php.*\?>`mUi', '', $str);

		// find and extract items to preserve
		global $p_items;
		if(!isset($p_items)) $p_items = array();
		$p_patterns = array(
			'`<\s*noparse[^>]*>(.*)</\s*noparse[^>]*>`smUi',
			'`<\s*script[^>]*>.*</\s*script[^>]*>`smUi');
		$p_func = create_function('$out', '
			global $p_items;
			$p_items[] = isset($out[1]) ? $out[1] : $out[0];
			return "<THIISpreserve".(count($p_items)-1)."/>";');
		foreach($p_patterns as $p) $str = preg_replace_callback($p, $p_func, $str);
		// parse code
		$str = preg_replace_callback('`(\n?)<code([^>]*)>(.*)(</\s*code[^>]*>)`smUi', array(&$this, 'parse_code'), $str);

		// hr
		$str = preg_replace('`___+`', '<hr />', $str);
		// lists
		$str = preg_replace_callback('`(?:\n(?:  |\t)+[-*][^\n]*)+`', array(&$this, 'parse_list'), $str);

		// find and render markups
		$markup_list = array('**'=>'**', '//'=>'//', '__'=>'__', '\'\''=>'\'\'', '{{'=>'}}', 'href="[['=>']]"', '[['=>']]', '(('=>'))');
		$markup_pattern = '`(\*\*|(?<!:)//|__|\'\'|\{\{|\}\}|\[\[|\]\]|\(\(|\)\))`';
		$markup_matches = preg_split($markup_pattern, $str, -1, PREG_SPLIT_DELIM_CAPTURE);

		$tree = $this->markup_tree_create($markup_list, $markup_matches);
		$str = $this->markup_tree_render($tree);

		// headers and paragraphs
		if($structure) {
			$str = $this->parse_headers($str);
			$str = $this->parse_breaks($str);
		}

		// dashes
		$str = preg_replace(
			array('`[^-](?<!<!)---(?!>)[^-]`', '`[^-](?<!<!)--(?!>)[^-]`'),
			array('&mdash;', '&ndash;'),
			$str);

		// retrieve preserved items
		foreach($p_items as $id => $item)
			$str = preg_replace('`<THIISpreserve'.$id.'/>`', $item, $str, 1);

		return $str;
	}

	/**
	 * Remove all markup in <i>string</i>.
	 *
	 * @param  string  $string The string containing markups.
	 * @param  boolean $headers Optional. If set to false, delete the headers content.
	 * @return string  The string without markups.
	 */
	function markup_del($string, $headers = true)
	{
		if(!$headers)
		{
			$del = array(
				'`<header[^>]*>.*</header>`',
				'`<h[1-6][^>]*>.*</h[1-6]>`i');
			$string = preg_replace($del, '', $string);
		}
		$find = array(
			'`\*\*(.*?)\*\*`',	// bold
			'`//(.*?)//`',		// italic
			'`__(.*?)__`',		// underline
			'`\'\'(.*?)\'\'`',	// monospace
			'`(?:___+)`',		// horizontals
			'`\[\[(.*?)\]\]`',	// link
			'`{{.*?}}`',		// object
			'`\(\(.*?\)\)`'		// footnote
		);

		$string = preg_replace($find, '\\1', $string);
		$string = strip_tags($string);

		return $string;
	}

	function markup_tree_create($markups, &$matches)
	{
		static $linkurl;
		$tree = array('childs'=>array());
		$queue = array();
		$match_id = 0;
		$errors = false;
		while(($value = array_shift($matches)) !== null)
		{
			// determine parent node
			if($len = count($queue)+1) {
				$parent =& $tree;
				while($len > 1) {
					$childsnbr = count($parent['childs']);
					$parent =& $parent['childs'][$childsnbr-1];
					$len--;
				}
			}
			// is markup
			if($match_id++ % 2) {
				// closing
				if(!isset($markups[$value]) || $value === end($queue))
				{
					if($value == ']]') $linkurl = null;
					$open = array_pop($queue);
					// syntax error
					if($value != $markups[$open]) {
						$message = '<span style="color:#C00;">'
							. '<u><a href="http://nliautaud.fr/thiis/syntax" style="color:#C00;" type="_blank">Thiis syntax error :</a></u> '
							. 'unexpected <b>'.$value.'</b>, expected <b>'.$markups[$open].'</b></span>.';
						$parent['childs'][$childsnbr-1] = $message;
						$errors = true;
					}
				}
				// opening
				else
				{
					if($content = array_shift($matches)) $childs = array($content);
					else $childs = array();
					$node = array('type'=>$value, 'childs'=>$childs);
					if($value == '[[') $linkurl = $content;
					if($linkurl) $node['link'] = $linkurl;;
					$parent['childs'][] = $node;
					$queue[] = $value;
					$match_id++;
				}
			}
			// not markup
			elseif($value) $parent['childs'][] = $value;
		}
		if($errors) $this->admin_msg('syntax', 'error');
		return $tree;
	}

	function markup_tree_render($node)
	{
		$html = '';
		foreach($node['childs'] as $child) {
			if(is_array($child))
			{
				$child_render = $this->markup_tree_render($child);
				// params
				$name = $child_render;
				$params = null;
				if($param_pos = strpos($child_render, '>')) {
					$name = substr($child_render, 0, $param_pos);
					$params = substr($child_render, $param_pos + 1);
				}
				switch($child['type'])
				{
					case '**': $html .= '<strong>' . $child_render . '</strong>'; break;
					case '//': $html .= '<em>' . $child_render . '</em>'; break;
					case '__': $html .= '<u>' . $child_render . '</u>'; break;
					case "''": $html .= '<code>' . $child_render . '</code>'; break;
					case '((': $html .= $this->parse_footnote($child_render); break;
					case '[[': $html .= $this->parse_link($name, $params); break;
					case '{{':
						$link = isset($child['link']) ? $child['link'] : null;
						$html .= $this->parse_object($name, $params, $link);
						break;
					default : $html .= $child_render;
				}
			}
			else $html .= $child;
		}
		return $html;
	}
	/**
	 * Find and execute PHP code in given string.
	 *
	 * @param  string $str The string to parse.
	 * @param  string $info Optional. Information about the data location for errors.
	 * @return string The string with evaluated PHP code.
	 */
	function php_render($str, $info = null)
	{
		ob_start();
		eval('?>' . $str);
		$str = ob_get_contents();
		ob_end_clean();

		$error_pattern = "`^<br />\n(<b>.* in <b>)/.*(</b> on line <b>[0-9]+</b>)<br />`";
		if(preg_match($error_pattern, $str, $error)) {
			$str = substr(preg_replace($error_pattern, '', $str), 7);
			if($this->user->rank)
				$this->infos .= $this->admin_msg('php', 'error', '<p>'.$error[1].$info.$error[2].'</p>');
			throw new Exception();
		}
		return $str;
	}
	/**
	 * Parse headers to create hierarchical structure.
	 * Surrounds headers by nested divs using related class and id.
	 *
	 * @param  string $str The content to parse.
	 * @return string The modified content.
	 */
	function parse_headers($str)
	{
		$str = "\n\n".str_replace("\r", '', $str)."\n\n";
		// convert markups
		$marks = array('=', '-', '#');
		foreach($marks as $i => $m) {
			$i++;
			$str = preg_replace_callback(
				array(
					// multi-lines
					"`(?:(?:\n\n)?$m{3,}\n?|\n\n)[ \t]*((?:.+?\n)+.*?)[ \t]*$m{4,}[ \t]*\n`",
					// one line
					"`\n[ \t]*$m{3,}[ \t]*([^\n]+?)[ \t]*$m*[ \t]*\n`"),
				create_function(
					'$out', 'return \'<h'.$i.'>\'.trim($out[1]).\'</h'.$i.'>\';'),
				$str);
		}
		// create structure
		for($i=1; $i<=6; $i++) {
			$pattern = "`(<\s*h$i.*>(.*)</\s*h$i>.*)(?=</div><div class=.h[1-$i]|<h[1-$i]|$)`siU";
			$function = create_function('$out',
				'return \'<div class="h'.$i.'" id="\'.string_to_id($out[2]).\'">\'.$out[1].\'</div>\';');
			$tmp = preg_replace_callback($pattern, $function, $str);
			if($tmp !== null) $str = $tmp;
		}
		return $str;
	}
	/**
	 * Replaces double line-breaks with paragraph elements (from WP).
	 *
	 * @param  string  $txt The text which has to be formatted.
	 * @param  boolean $br Convert remaining line-breaks into <br />'s.
	 * @return string  Text which has been converted into correct paragraph tags.
	 */
	function parse_breaks($txt, $br = true)
	{
		$txt = $txt . "\n"; // just to make things a little easier, pad the end
		$txt = preg_replace('`<br />\s*<br />`', "\n\n", $txt);
		// Space things out a little
		$allblocks = '(?:table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|select|option|form|map|area|blockquote|address|math|style|input|p|h[1-6]|hr|fieldset|legend|section|article|aside|hgroup|header|footer|nav|details|menu|summary)';
		$txt = preg_replace('`(<' . $allblocks . '[^>]*>)`', "\n$1", $txt);
		$txt = preg_replace('`(</' . $allblocks . '>)`', "$1\n\n", $txt);
		$txt = str_replace(array("\r\n", "\r"), "\n", $txt); // cross-platform newlines
		if(strpos($txt, '<object') !== false){
			$txt = preg_replace('`\s*<param([^>]*)>\s*`', "<param$1>", $txt); // no pee inside object/embed
			$txt = preg_replace('`\s*</embed>\s*`', '</embed>', $txt);
		}
		$txt = preg_replace("`\n\n+`", "\n\n", $txt); // take care of duplicates
		// make paragraphs, including one at the end
		$txts = preg_split('`\n\s*\n`', $txt, -1, PREG_SPLIT_NO_EMPTY);
		$txt = '';
		foreach($txts as $tinkle){
			$txt .= '<p>' . trim($tinkle, "\n") . "</p>\n";
		}
		$txt = preg_replace('`<p>\s*</p>`', '', $txt); // under certain strange conditions it could create a P of entirely whitespace
		$txt = preg_replace('`<p>([^<]+)</(div|address|form)>`', "<p>$1</p></$2>", $txt);
		$txt = preg_replace('`<p>\s*(</?' . $allblocks . '[^>]*>)\s*</p>`', "$1", $txt); // don't pee all over a tag
		$txt = preg_replace("`<p>(<li.+?)</p>`", "$1", $txt); // problem with nested lists
		$txt = preg_replace('`<p><blockquote([^>]*)>`i', "<blockquote$1><p>", $txt);
		$txt = str_replace('</blockquote></p>', '</p></blockquote>', $txt);
		$txt = preg_replace('`<p>\s*(</?' . $allblocks . '[^>]*>)`', "$1", $txt);
		$txt = preg_replace('`(</?' . $allblocks . '[^>]*>)\s*</p>`', "$1", $txt);
		if($br) {
			$txt = preg_replace_callback('/<(script|style|noparse).*?<\/\\1>/s',
				create_function('$out','return str_replace("\n", "<WPPreserveNewline />", $out[0]);'),
				$txt);
			$txt = preg_replace('|(?<!<br />)\s*\n|', "<br />\n", $txt); // optionally make line breaks
			$txt = str_replace('<WPPreserveNewline />', "\n", $txt);
		}
		$txt = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*<br />!', "$1", $txt);
		$txt = preg_replace('!<br />(\s*</?(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)[^>]*>)!', '$1', $txt);
		if(strpos($txt, '<pre') !== false)
		{
			$txt = preg_replace_callback('`<pre[^>]*>.*?</pre>`is',
				create_function('$out','return str_replace(array("<br />","<p>","</p>"),array("","\n",""),$out[0]);'),
				$txt );
		}
		$txt = preg_replace( "|\n</p>$|", '</p>', $txt );

		return $txt;
	}
	/*
	 * Return a HTML version of a given object markup. (parse callback)
	 *
	 * @param mixed $input A string to parse or the object description to render.
	 * @param string $p The parameters separated by commas
	 * @return string The HTML object.
	 */
	function parse_object($name, $p = null, $link = null)
	{
		// Alignment
		$push_left = $name[0] == ' ';
		$push_right = substr($name.$p, -1) == ' ';
		if($push_left && $push_right) $alignment = 'center';
		elseif($push_left) $alignment = 'right';
		elseif($push_right) $alignment = 'left';
		else $alignment = 'inline';

		$name = strtolower(trim($name));
		$p = $p ? explode(';', trim($p)) : array();

		// Size
		$size_pattern = '`^(\d+(?:px|%))$`i';
		if(!$w = preg_array_shift($p, $size_pattern)) $w = false;
		if(!$h = preg_array_shift($p, $size_pattern)) $h = false;
		if($w || $h)
		{
			$size = 'style="';
			if($w) $size .= 'width:' . $w . ';';
			if($h) $size = 'height:' . $h . ';';
			$size .= '"';
		} else $size = "";

		// Images
		if(false !== ($pos = strpos($link, '>'))) $link = substr($link, 0, $pos);
		$url_pattern = '`^(https?://)?([^/?#]*)?([^?#]*\.(?:jpe?g|gif|png))(?:\?([^#]*))?(?:#(.*))?$`i';
		if(preg_match($url_pattern, $name, $out))
		{
			//if(empty($out[1])) $name = $this->root() . '/' . $name;

			if(!$crop = preg_array_shift($p, '`^crop$`')) $crop = false;
			if(!$caption = typed_array_shift($p, 'string')) $caption = false;
			return $this->html_image($name, $w, $h, $crop, $alignment, $caption, $link);
		}
		// CONSTANT
		if(defined(strtoupper($name)))
			return constant(strtoupper($name));

		// method
		$method = 'html_' . $name;
		if(method_exists($this, $method)) {

			$p = string_to_value($p);
			// 6 optional parameters please
			switch(count($p)) {
				case 1: return $this->$method($p[0]);
				case 2: return $this->$method($p[0], $p[1]);
				case 3: return $this->$method($p[0], $p[1], $p[2]);
				case 4: return $this->$method($p[0], $p[1], $p[2], $p[3]);
				case 5: return $this->$method($p[0], $p[1], $p[2], $p[3], $p[4]);
				case 6: return $this->$method($p[0], $p[1], $p[2], $p[3], $p[4], $p[5]);
			}
			return $this->$method();
		}

		// Other objects
		switch($name)
		{
			// Videos
			case 'youtube' :
			case 'vimeo' :
			case 'dailymotion' :
				$src = array(
					'youtube'		 => 'http://youtube.com/embed/',
					'vimeo'			 => 'http://player.vimeo.com/video/',
					'dailymotion' => 'http://dailymotion.com/embed/video/');
				if(empty($p[0])) return;
				else return '<div class="video"><iframe '
				. 'class="' . $alignment . ' ' . $name . '" '
				. 'frameborder="0" allowfullscreen ' . $size
				. 'src="' . $src[$name] . $p[0] . '"></iframe></div>';
				break;

			// Gallery
			case 'gallery' :
				// Columns and rows, or maximum count
				if(!$cols = intval(preg_array_shift($p, '`^\d+$`'))) $cols = 100;
				$rows = intval(preg_array_shift($p, '`^\d+$`'));
				// Name filter
				if(!$fltr = preg_array_shift($p, '`\*`')) $fltr = '*';
				// Bool parameters
				$path = array_shift($p);
				$revr = preg_array_shift($p, '`^reverse$`i') ? true : false;
				$rand = preg_array_shift($p, '`^random$`i') ? true : false;
				$link = preg_array_shift($p, '`^link$`i') ? true : false;
				return $this->html_gallery($path, $alignment, $w, $h, $cols, $rows, $fltr, $revr, $rand, $link, $p);
				break;
		}


		// PAGE THINGS
		if( strpos($name, 'page.') === 0 )
		{
			// method
			$request = substr($name, strlen('page.'));
			if( strpos($request, '.') === false ) {
				$path = PAGE_PATH;
			} else {
				$last_dot = strrpos($request, '.');
				$path = substr($request, 0, $last_dot);
				$path = str_replace('.', '/', $path);
				$request = substr($request, $last_dot);
			}
			$method = 'page_' . $request;
			if( method_exists($this, $method) ) {
				return $this->$method($path);
			}

			// meta info
			$page = $this->page($path);
			if( isset($page->meta[$request]) )
				return $page->meta[$request][0];
		}

		// CONFIG VALUE
		$name = str_replace('.', '/', $name);
		return $this->config($name);
	}
	/*
	 * Return a HTML version of a given footnote markup.
	 *
	 * @param string $note The markup content
	 * @return string The HTML.
	 */
	private function parse_footnote($note)
	{
		static $id; $id++;

		// get context in case of ((context>>note))
		$context = '';
		if(($pos = strrpos($note, '>>')) !== false) {
			$context = substr($note, 0, $pos);
			$note = substr($note, $pos+2);
		}
		$this->footnotes[] = $note;

		// render footnote anchor
		$fna = '<span id="fna-'.$id.'" class="note">';
		$fna.= $context;
		$fna.= $this->parse_link('#fn-' . $id, '<sup>' . $id . '</sup>');
		$fna.= '</span>';
		return $fna;
	}
	function parse_link($name = null, $alias = null)
	{
		if(empty($name)) return;

		$url_pattern = '`^(?:([hH][tT][tT][pP][sS]?:)\/\/)?'	// protocol
		. '(?:([\w\.\-]+(?:\:[\w\.\&%\$\-]+)*)@)?'	// email
		. '((?:(?:\.?[^\s\(\)\<\>\\\'\"\.\[\]\,@;:]+)+\.[a-zA-Z]{2,6})|'	// (sub)domain
		. '((([01]?\d{1,2}|2[0-4]\d|25[0-5])\.){3}([01]?\d{1,2}|2[0-4]\d|25[0-5])))'	// ip
		. '(?:\b\:(?:6553[0-5]|655[0-2]\d|65[0-4]\d{2}|6[0-4]\d{3}|[1-5]\d{4}|[1-9]\d{0,3}|0)\b)?'	// port
		. '(?:(?:\/[^\/][\w\.\,\?\'\\\/\+&%\$#\=\:~_\-@]*)*[^\.\,\?\"\'\(\)\[\]!;<>{}\s\x7F-\xFF])?$`'; // params

		// interwiki
		$wikis = array(
			'thiis' => 'http://nliautaud.fr/thiis/',
			'wikipedia' => 'http://wikipedia.org/wiki/',
			'google' => 'http://google.com/search?q=',
			'dokuwiki' => 'http://dokuwiki.org/');
		$wikis_alias = array(
			'is' => 'thiis',
			'wp' => 'wikipedia',
			'go' => 'google',
			'dw' => 'dokuwiki');
		if(isset($wikis[$name])) $wiki = $name;
		elseif(isset($wikis_alias[$name])) $wiki = $wikis_alias[$name];
		if(isset($wiki))
		{
			$class = 'external ' . $wiki;
			if(!$alias) $alias = $wiki;
			else {
				$alias = str_replace('_', ' ', $alias);
				$search = str_replace(' ', '_', $alias);
				if(preg_match('`^[a-z]{2}:`', $alias)) {
					$class .= ' ' . substr($alias, 0, 2);
					$alias = substr($alias, 3) . ' <span>(' . substr($alias, 0, 2) . ')</span>';
				}
			}
			$href = $wikis[$wiki] . @$search;
			$title = $wiki;
		}
		// special
		elseif($name[0] == '?') {
			if($name[0] == '?') $href = $name;
			$name = substr($name, 1);
			if(!$alias) $alias = $name;
			$title = '';
			$class = $name;
		}
		// external
		elseif(!$name || preg_match($url_pattern, $name, $out))
		{
			// a content file path
			if(file_exists($name))
				return ROOT_URL . 'content/' . $name;

			// an external link
			$class = 'external';
			if(!$alias) $alias = $name;
			if(!empty($out[2])) $name = 'mailto:' . $out[0];
			elseif(empty($out[1])) $name = 'http://' . $name;
			$title = '';
			$href = $name;
		}
		// page [[name]] or [[name#anchor]] or [[#anchor]]
		elseif(preg_match('`^([a-z0-9/_.-]*)(?:#(.+))?$`', $name, $out))
		{
			if(!isset($out[2])) $out[2] = '';
			list(, $path, $anchor) = $out;
			if(!$path) $path = PAGE_PATH;
			$exists = $this->page_exists($path);

			if(empty($alias)) {
				if($exists) $alias = $this->page_title($path);
				else $alias = basename($path);
				if(!empty($anchor)) $alias = $anchor.' ('.$alias.')';
			}
			// Class
			$class = 'page ' . string_to_id($path);
			if(!$exists) $class .= ' broken';
			elseif($path == PAGE_PATH) $class .= ' current';

			$anchor = string_to_id($anchor);
			if($path == PAGE_PATH && $anchor) $href = '#' . $anchor;
			else $href = $this->page_url($path, $anchor);
			$title = '';
		}
		// unknown
		else $href = $title = $class = '';

		// image link
		if(strpos($alias, '<figure') !== false) return $alias;
		// normal link
		if($href)  $href  = 'href="'.$href.'"';
		if($title) $title = 'title="'.$title.'"';
		if($class) $class = 'class="'.$class.'"';
		return "<a $href $title $class>$alias</a>";
	}
	/*
	 * Return a HTML version of a given list markup. (from parse callback)
	 *
	 * Markup guide : (indented by at least two spaces or a tabulation)
	 *     * unordered item
	 *     - ordored item
	 *     ** term
	 *     -- definition
	 *     ** term ** definition
	 *     -- term -- def
	 *
	 * @param string $code The list markup.
	 * @return string The HTML list.
	 */
	function parse_list($code)
	{
		preg_match_all('`((?:  |\t)+)(--|\*\*|[-*])(.*)`', $code[0], $out, PREG_SET_ORDER);
		if(!$out) return '';
		$html = '';
		$last_lvl = 0;
		$last_type = array();
		foreach($out as $o)
		{
			list(, $spaces, $opening, $content) = $o;
			$lvl = strlen($spaces);
			if($lvl != $last_lvl)
			{
				if($opening == '*') $type = 'ul';
				elseif($opening== '-') $type = 'ol';
				else $type = 'dl';

				if($lvl > $last_lvl)
				{
					$html .= "<$type>";
					$last_type[] = $type;
				}
				while($lvl < $last_lvl)
				{
					$html .= '</' . array_pop($last_type) . '>';
					$last_lvl -= 2;
				}
			}
			// definition lists items
			if($type == 'dl') {
				// term/definition
				$pos = strpos($content, $opening);
				if($pos !== false) {
					$html .= '<dt>' . substr($content, 0, $pos) . '</dt>';
					$html .= '<dd>' . substr($content, $pos + 2) . '</dd>';
				}
				// term only
				elseif($opening == '**') $html .= '<dt>' . $content . '</dt>';
				// definition only
				else $html .= '<dd>' . $content . '</dd>';
			}
			// classical lists items
			else $html .= '<li>' . $content . '</li>';
			$last_lvl = $lvl;
		}
		return $html . "</$type>";
	}
	/*
	 * Return a HTML version of a given code markup. (from parse callback)
	 *
	 * Markup guide :
	 *     \n<code foo>\n\t bar \n\t baz \n</code> block code (<pre foo><code>\t bar \n\t baz</code></pre>)
	 *     <code foo>bar</code> inline code (<code>bar</code>)
	 *
	 * @param array $markup The code markup.
	 * @return string The HTML code.
	 */
	function parse_code($markup)
	{
		global $p_items;
		list(, $spaces, $class, $content, $end) = $markup;
		$content = htmlentities($content);

		if($spaces == "\n") {
			$begin = "<pre".$class."><code>";
			$content = trim($content, "\r\n");
			$end .= "</pre>";
		}
		else $begin = "<code".$class.">";

		$p_items[] = $spaces.$begin.$content.$end;
		return "<THIISpreserve".(count($p_items)-1)."/>";
	}

	/* SESSIONS */

	/*
	 * Try to login with name/pass and return bool result.
	 *
	 * @param string $param $name the login name
	 * @param string $param $pass the login password
	 */
	function login($name, $pass, $fp)
	{
		// for every user with this name
		$user_path = $this->config_find($name, null, 'user');
		foreach( $user_path as $path )
		{
			// if one matches, register it
			if( $pass == $this->config($path) )
			{
				$this->user = $path;
				$_SESSION[$fp]['name'] = $name;
				$_SESSION[$fp]['password'] = $pass;
				return true;
			}
		}
		return false;
	}
	/*
	 * Return session fingerprint hash.
	 *
	 * @return string
	 */
	function fingerprint()
	{
		return 'thiis' . sha1('thiis' .
		$_SERVER['HTTP_USER_AGENT'] .
		$_SERVER['REMOTE_ADDR'] .
		$_SERVER['SCRIPT_NAME'] .
		session_id());
	}
	/*
	 * Check logout, login or login form display.
	 */
	function check_login()
	{
		$fp = $this->fingerprint();

		// logout action
		if(isset($this->post['logout'])) {
			unset($_SESSION[$fp]);
			return;
		}

		// login action
		if(isset($this->post['login'])
		&& isset($this->post['password'])) {
			$pass = sha1($this->post['password']);
			return $this->login($this->post['login'], $pass, $fp);
		}

		// transparent login (already logged)
		if(isset($_SESSION[$fp])) {
			$name = $_SESSION[$fp]['name'];
			$pass = $_SESSION[$fp]['password'];
			if($this->login($name, $pass, $fp)) return true;
			unset($_SESSION[$fp]);
			return false;
		}
	}
	/*
	 * Return login form.
	 */
	function html_login_form()
	{
		$action = HAS_ADMIN ? ROOT_URL.'admin' : '';
		if(!isset($this->user)) return '
		<form id="login-form" method="post" action="' . $action . '" >
			<input type="text" name="login" class="field field-login" />
			<input type="password" name="password" class="field field-password" />
			<input type="submit" class="btn" value="login" />
		</form>';

		return basename($this->user) . ' (' . dirname($this->user) . ')
		<form id="logout-form" method="post" action="" >
			<input type="hidden" name="logout" />
			<input type="submit" class="btn" value="logout" />
		</form>';
	}

	function not_authorized( $path )
	{
		$restrict_to = $this->config('page/'.$path.'/allow_view');
		$same_start = strpos($this->user.'/', $restrict_to.'/') === 0;
		if( $restrict_to && !$same_start )
			return true;
		return false;
	}



	/* PAGES */


	function page( $path )
	{
		if(!$path) $path = PAGE_PATH;
		$realpath = CONTENT_DIR.$path;

		if( isset($this->pages[$path]) ) {
			return $this->pages[$path];
		}
		// get raw file content
		$raw = @file_get_contents($realpath.'.is');
		if( !$raw ) return false;
		$raw_content = $raw = trim($raw);

		// get page meta
		$meta = null;
		$meta_start = strpos($raw, "/*\n");
		$meta_close = strpos($raw, "\n*/");
		if( $meta_start === 0 && $meta_close !== false  )
		{
			$raw_content = substr($raw, $meta_close + 3);
			$meta = substr($raw, 3, $meta_close - 3);
			$meta = explode("\n", $meta);
			$meta = array_filter($meta);
			foreach($meta as $i => $m) {
				list($key, $val) = explode(':', $m, 2);
				$key = trim(strtolower($key));
				$val = trim($val);
				$val = str_replace('(propagate)', '', $val, $propagate);
				$val = $this->parse($val);
				$meta[$key] = array($val, $propagate > 0);
			}
		}

		// get page cache, or false
		$content = @file_get_contents($realpath.'.html');

		// register
		$this->pages[$path] = array(
			'raw' => $raw,
			'meta' => $meta,
			'raw_content' => $raw_content,
			'content' => $content
		);
		return $this->pages[$path];
	}
	function page_list( $path )
	{
		$path = $this->first_realpath(CONTENT_DIR, $path);
		if( $path ) $path .= '/';
		$files = glob($path . '*.is', GLOB_NOSORT);
		$files = array_diff($files, array('.', '..'));

		//array_multisort(array_map('filemtime', $files), SORT_NUMERIC, SORT_DESC, $files);

		return $files;
	}
	/*
	 * Return a page name.
	 */
	function page_name( $path )
	{
		return basename($path);
	}

	function page_exists( $path )
	{
		if(!$path) $path = PAGE_PATH;

		// from memory
		$content = $this->config('page/'.$path.'/content');
		if( $content ) return true;

		// from files
		$path = CONTENT_DIR . $path;
		if( file_exists($path.'.is') ) {
			return true;
		}
		// nop
		return false;
	}
	/*
	 * Return a page content, or 404.
	 */
	function page_content( $path )
	{
		if($this->not_authorized($path))
			return $this->page_content('403');

		// page exists
		$page = $this->page($path);
		if( $page )
		{
			// in memory
			if( $page['content'] ) return $page['content'];

			// or parse & memorize
			$content = $this->parse($page['raw_content'], true, true, 'page "' . $path . '"');
			$this->pages[$path]['content'] = $content;
			return $content;
		}

		// 403
		if( $path == '403' ) {
			header('HTTP/1.0 403 Forbidden');
			return '<h1>403</h1>';
		}
		// 404
		if( $path != '404' ) {
			return $this->page_content('404');
		}
		// 404 of 404
		header('HTTP/1.0 404 Not Found');
		return '<h1>404</h1>';
	}
	/*
	 * Return the page title as defined in the header,
	 * or the first headline, or the page name.
	 *
	 * @param string $path The page path.
	 * @return string The page title.
	 */
	function page_title( $path )
	{
		$page = $this->page($path);

		if( isset($page->meta['title']) )
			return $page->meta['title'][0];

		// get cache file or parse page titles
		$content = $page['content'];
		if( !$content ) {
			$content = $this->parse_headers($page['raw_content']);
		}
		// find, memorize and return
		if( false !== ($a = strpos($content, '<h1>'))
		 && false !== ($b = strpos($content, '</h1>', $a+1)) )
		{
			$title = substr($content, $a+4, $b - ($a+4));
			$this->pages[$path]['meta']['title'][0] =  $title;
			$this->pages[$path]['meta']['title'][1] =  false;
			return $title;
		}
		// nothing found
		return $this->page_name($path);
	}
	/*
	 * Return the page absolute url, according to rewriting.
	 *
	 * @param string $path The page path.
	 * @param string $anchor Optionnal. The anchor.
	 */
	function page_url($path, $anchor = '')
	{
		$mark = HAS_REWRITE ? '' : '?';
		$anchor = $anchor ? '#'.$anchor : $anchor;
		// prettier without "index"
		$path = str_replace('/index', '', $path);
		if($path == 'index') $path = '';

		return ROOT_URL.$mark.$path.$anchor;
	}
	/*
	 * Return the footnotes of a page.
	 */
	function page_footnotes( $page )
	{
		// if not already processed
		// quick parse any ((note)) in page content
		if( $page != PAGE_PATH || empty($this->footnotes) )
		{
			$content = $this->page_content($page);
			if(!$content) return false;
			preg_match_all('`\(\((.*)\)\)`U', $content, $out);
			foreach($out[1] as $note) $this->parse_footnote($note);
		}

		// render
		$fn = '';
		foreach( $this->footnotes as $id => $note )
		{
			$id++;
			$fn.= '<div id="fn-'.$id.'" class="footnote">';
			$fn.= '<sup>'.$this->parse_link('#fna-'.$id, $id.' ').'</sup> ';
			$fn.= $this->parse($note);
			$fn.= '</div>';
		}
		return $fn;
	}

	/* HTML-OUTPUTS */

	/*
	 * Return a nested list of pages links.
	 * @param string $parent list any childs of this parent. empty by default. (optionnal)
	 * @param string $sort_field sort list along this field (optionnal)
	 * @param int $max_lvl nesting limit level. null by default. (optionnal)
	 * @param int $pages use this nested pages names list. (optionnal)
	 * @return string
	 */
	public function html_pages_list(
		$parent = '', $deploy = true,
		$sort_field = null, $sort_method = null,
		$max_level = null
	) {
		$pages = $this->page_list($parent);
		$html = '<ul>';
		foreach($pages as $page)
		{
			$page = pathinfo($page, PATHINFO_FILENAME);
			$page = $parent.$page;

			// display?
			$exclude = array('403', '404');
			if( in_array($page, $exclude)
			 || $this->config('page/'.$page.'/hide')
			 || $this->not_authorized($page) ) {
				continue;
			}

			$dir_exists = is_dir($page);

			if($dir_exists) $html .= '<li class="parent">';
			else $html .= '<li>';

			$html .= $this->parse_link($page);

			if($dir_exists)
				$html .= $this->html_pages_list($page.'/', $sort_field, $sort_method, $max_level);

			$html .= '</li>';
		}
		$html .= '</ul>';
		return $html;
	}
	/*
	 * Return a title/navigation breadcrumb according to the page hierarchy.
	 * @return array Plain text (for title meta), html (with links), root link (category)
	 */
	function html_breadcrumb($hierarchy = null, $sep = ', ', $end = ' : ') {
		if(!is_array($hierarchy))
			$hierarchy = $this->hierarchy('page', PAGE_PATH);
		$nbr = count($hierarchy);

		$html = $plain = '';
		foreach($hierarchy as $id => $p) {
			// separators
			if($nbr > 1) {
				if($id < $nbr - 1) {
					if($id) {
						$plain.= $sep;
						$html .= $sep;
					}
				} else {
					$plain.= $end;
					$html .= $end;
				}
			}
			// titles
			$ttl = $this->page_title($p);
			$plain.= $ttl;
			if($id < $nbr-1)
				$html .= $this->parse_link($p);
			else
				$html .= '<strong><a id="title" href="#top">' . $ttl . '</a></strong>';
		}
		return array('plain' => $plain, 'html' => $html);
	}
	/*
	 * Return an HTML image.
	 *
	 * @param string $url The image url.
	 * @param int $width Optionnal. If set, force the image display width.
	 * @param int $height Optionnal. If set, force the image display height.
	 * @param string $alignment Optionnal. An additional image alignment (inline|left|right|center).
	 * @param string $caption Optionnal. If set, return an image box.
	 */
	function html_image(
		$url,
		$width = false, $height = false, $crop = false,
		$alignment = '', $caption = '',
		$linkto = false, $style = ''
	){
		if($width || $height) {
			$url = '?image=' . $url;
			if($width) $url .= '&w=' . $width;
			if($height) $url .= '&h=' . $height;
			if($crop) $url .= '&crop';
		}

		switch($alignment) {
			case 'inline' : $float = 'display:inline-block;'; break;
			case 'center' :
				$float = 'display:block; text-align: center; margin-left:auto; margin-right:auto;';
				break;
			case 'right' : case 'left' : $float = 'float:'. $alignment . ';'; break;
			default : $float = '';
		}
		if(!empty($caption)) $float = '';

		if($linkto) {
			$image = $this->parse_link($linkto, '<img src="'.$url.'" alt="'.basename($url).'" />');
			if($float) $image = str_replace('><img', ' style="'.$float.' '.$style.'"><img', $image);
		}
		else $image = '<img src="'.$url.'" alt="'.basename($url).'" class="'.$alignment.'" style="'.$float.' '.$style.'" />';

		if(!empty($caption))
			return "<figure class=\"$alignment\" style=\"$float\">
						$image
						<figcaption>$caption</figcaption>
					</figure>";
		 return $image;
	}
	/*
	 * Return an HTML gallery of images.
	 *
	 * @param string $path Optionnal. The directory path containing images, relative to root.
	 * @param int $algn Optionnal. Additional images classes for alignment.
	 * @param int $wdth Optionnal. If set, force the images width.
	 * @param int $hght Optionnal. If set, force the images height.
	 * @param int $cols Optionnal. If set, define the number of images (or columns).
	 * @param int $rows Optionnal. If set, define the number of rows of the table gallery.
	 * @param string $filter Optionnal. If set, filter the images by name. Stars are wildcards.
	 * @param bool $name Optionnal. If set to true, display images boxes with their names.
	 * @param bool $revr Optionnal. If set to true, reverse images order.
	 * @param bool $rand Optionnal. If set to true, randomize images order.
	 */
	function html_gallery(
		$path = '', $algn = '',
		$wdth = false, $hght = false,
		$cols = false, $rows = false,
		$fltr = false,
		$revr = false, $rand = false,
		$link = false, $info = false
	){
		/*
		// Define maximum number
		$max = $rows ? $cols * $rows : $cols;
		// Replace stars by preg wildcards.
		$fltr = strtr(preg_quote($fltr), array('\*\*' => '\*', '\*' => '.*'));
		if($files = $this->file_get($path, 'image'))
		{
			// Sort
			if($rand) $files = $this->md_shuffle($files);
			else if($revr) $files = array_reverse($files);

			$out = '<div class="gallery">';
			if($rows) $out .= '<table><tr>';
			$count = 0;
			foreach($files as $name => $img)
			{
				$basename = basename($name);
				if(!$fltr || preg_match('`^' . $fltr . '$`i', $basename))
				{
					if($max && $count++ >= $max) break;

					$caption = '';
					if(!empty($info)) {
						foreach($info as $i) {
							if($i == 'name')
								$img[$i] = substr($basename, 0, strrpos($basename, '.'));
							if(isset($img[$i]) && $i != 'type' && $i != 'preview')
								$caption .= '<div class="' . $i . '">' . $this->parse($img[$i]) . '</div>';
						}
					}

					$url = $this->absroot($name);
					$linkto = $link ? $url : false;
					$image = $this->html_image($url, $wdth, $hght, $algn, $caption, $linkto);

					if($rows) {
						$out .= '<td>' . $image . '</td>';
						if($count%$cols == 0) $out .= '</tr><tr>';
					}
					else $out .= $image;
				}
			}
			if($rows) return $out . '</tr></table></div>';
			else return $out . '</div>';
		}
	*/
	}
	/*
	 * Return an HTML table of content based on a content analysis.
	 *
	 * @param string $name Optionnal. If set analyse this page instead of current page.
	 * @param int $depth Optionnal. The maximum headers level to display, by default 4.
	 * @param int $required Optionnal. The minimum number of elements required to return the toc. By default 3.
	 * @param string $class Optionnal. Additionnal class, like alignment.
	 * @param string $content Optionnal. If set, analyse this instead of a page content.
	 * @return string The HTML table of content.
	 */
	function html_toc(
		$name = null,
		$depth = 4,
		$required = 3,
		$class = '',
		$content = null
	){
		if(empty($name)) $name = PAGE_PATH;
		if($depth < 1 || $depth > 6) return '';
		if(!$content) $content = $this->page_content($name);

		// Strip scripts and code
		$content = preg_replace('`<\s*noparse[^>]*>.*?</\s*noparse[^>]*>`si', '', $content);
		$content = preg_replace('`<\s*script[^>]*>.*?</\s*script[^>]*>`si', '', $content);
		$content = preg_replace('`<\s*code[^>]*>.*?</\s*code[^>]*>`si', '', $content);

		$markup = '(?<!=)[=]{'.(8-$depth).',7}(?!=)';
		$pattern = '`(?:<h([1-'.$depth.'])[^>]*>|(' . $markup . '))';
		$pattern .= '(.*?)';
		$pattern .= '(?:</h[1-'.$depth.'][^>]*>|' . $markup . ')`is';

		preg_match_all($pattern, $content, $out, PREG_SET_ORDER);

		$headers = array();
		foreach($out as $h)
		{
			if(!empty($h[2])) $lvl = 8 - strlen($h[2]);
			else $lvl = $h[1];
			$headers[$h[3]] = $lvl;
		}
		if(count($headers) < $required) return '';

		$html = '<div class="toc ' . $class . '">';

		$prev_lvl = 0;
		foreach($headers as $title => $lvl)
		{
			if($prev_lvl > $lvl) $html .= '</li></ul></li><li>';
			elseif($prev_lvl == $lvl) $html .= '</li><li>';
			elseif($prev_lvl < $lvl) $html .= '<ul><li>';
			$html .= $this->parse_link($name.'#'.$title, $title);
			$prev_lvl = $lvl;
		}
		return $html . '</div>';
	}
	/*
	 * Return a xml RSS feed of page.
	 * @return string
	 */
	function rss()
	{
		// Header
		header("Content-Type: application/rss+xml");
		$xml = '<?xml version="1.0" encoding="utf-8"?>';
		$xml .= "\n".'<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">';
		$xml .= "\n".'<channel>';
		$xml .= "\n".'<atom:link href="'.SITEURL.'?rss" rel="self" type="application/rss+xml" />';

		$last_edit = $this->find('page', null, null, 1, SORT_DESC, 'date', true);
		$last_edit = $this->get('page', $last_edit, 'date');

		// Channel description
		$xml .= "\n".'<title>' . $this->setting('site_title') . '</title>';
		$xml .= "\n".'<link>' . SITEURL . '</link>';
		$xml .= "\n".'<description>' . $this->setting('site_description') . '</description>';
		$xml .= "\n".'<lastBuildDate>' . date('r', intval($last_edit)) . '</lastBuildDate>';
		$xml .= "\n".'<language>' . $this->language() . '</language>';

		// Items
		foreach($this->get('page') as $name => $page) {
			$url = $this->page_url($name);

			$content = $this->parse($page['content'], false);
			$content = $this->markup_del($content, false);
			$content = $this->excerpt($content, 300, '...');
			$content = html_entity_decode(strip_tags($content), ENT_QUOTES, 'UTF-8');

			$xml .= "\n".'<item>';
			$xml .= "\n\t".'<title>' . html_entity_decode($this->page_title($name), ENT_QUOTES, 'UTF-8'). '</title>';
			$xml .= "\n\t".'<link>' . $url . '</link>';
			$xml .= "\n\t".'<guid>' . $url . '</guid>';
			$xml .= "\n\t".'<pubDate>' . $page['date'] . '</pubDate>';
			$xml .= "\n\t".'<description>' . $content . '</description>';
			$xml .= "\n".'</item>';
		}
		$xml .= '</channel></rss>';
		return $xml;
	}


	/* CHECKERS */

	/*
	 * Check if a string is reserved by the system.
	 * @param string $str the string
	 * @return bool
	 */
	function is_reserved_name($str) {
		$reserved = array('name','value','content','parent','rank','password','timestamp');
		if(empty($str) || in_array($str, $reserved)) return true;
		return false;
	}
	/*
	 * Check if user have equal or superior right
	 * for an action on given <i>type</i>.
	 * @param string $type the data type
	 * @return bool
	 */
	function is_granted($type) {
		if(!isset($this->user->rank)) return false;
		return $this->user->rank >= $this->get('right', $type);
	}

	/* FAST GETTERS */

	/*
	 * Return the next vacant name for an item of data <i>type</i>, as "data_##".
	 *
	 * @param string $type The data type.
	 * @return string The name.
	 */
	function vacant_name($type)
	{
		$item = rtrim($type, 's');
		if($items = $this->find($type)) {
			rsort($items);
			if($id = preg_array_shift($items, '`^' . $item . '_(\d+)$`')) {
				$id = intval($id) + 1;
				if($id < 10) $id = '0'.$id;
			} else $id = '01';
			return $item . '_' . $id;
		}
		return false;
	}
}
















class Image
{
   var $path;
   var $image;
   var $type;
   var $width;
   var $height;

   function Image($path)
   {
		$this->path = $path;
		$info = @getimagesize($path);
		if(!$info) throw new Exception('Unknown Image');
		list($this->width, $this->height, $this->type) = $info;
   }
   function load()
   {
		switch($this->type) {
			case IMAGETYPE_JPEG : $this->image = imagecreatefromjpeg($this->path); break;
			case IMAGETYPE_GIF :  $this->image = imagecreatefromgif($this->path); break;
			case IMAGETYPE_PNG :  $this->image = imagecreatefrompng($this->path); break;
		}
   }
   function save($path, $type = null, $compression = 75, $permissions = null)
   {
		if($type === null) $type = $this->type;
		switch($type) {
			case IMAGETYPE_JPEG : imagejpeg($this->image, $path, $compression); break;
			case IMAGETYPE_GIF :  imagegif($this->image, $path); break;
			case IMAGETYPE_PNG :  imagepng($this->image, $path); break;
		}
		if($permissions != null) chmod($path,$permissions);
   }
   function output($type = null)
   {
		if($type === null) $type = $this->type;
		// Type and cache control
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($this->path)) . ' GMT');
		header('Content-Type: '. $type);
		switch($type) {
			case IMAGETYPE_GIF :
			case IMAGETYPE_PNG :
				imagepng($this->image); break;
			case IMAGETYPE_JPEG :
				imagejpeg($this->image); break;
		}
   }
   function resize($width = null, $height = null, $crop = false)
   {
		if(!$width && !$height) return;
		// compute sizes
		if($crop){
			if($width == null) $width = $this->width;
			if($height == null) $height = $this->height;
			$ratio = max($width/$this->width, $height/$this->height);
			$y = ($this->height - $height / $ratio) * .5;
			$this->height = $height / $ratio;
			$x = ($this->width - $width / $ratio) * .5;
			$this->width = $width / $ratio;
		}
		else{
			if(!$width) $width = $this->width * $height/$this->height;
			if(!$height) $height = $this->height * ($width/$this->width);
			$x = $y = 0;
		}
		$new = imagecreatetruecolor($width, $height);
		// preserve transparency
		if($this->type == IMAGETYPE_GIF or $this->type == IMAGETYPE_PNG){
			imagealphablending($new, false);
			imagefill($new, 0, 0, imagecolorallocatealpha($new, 0, 0, 0, 127));
			imagesavealpha($new, true);
		}
		imagecopyresampled($new, $this->image, 0, 0, $x, $y, $width, $height, $this->width, $this->height);
		$this->image = $new;
		$this->width = imagesx($this->image);
		$this->height = imagesy($this->image);
   }
	function is_animated() {
		if($this->type != IMAGETYPE_GIF
		|| !($fh = @fopen($this->path, 'rb')))
			return false;
		$count = 0;
		while(!feof($fh) && $count < 2) {
			$chunk = fread($fh, 1024 * 100); //read 100kb at a time
			$count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk, $matches);
		}
		fclose($fh);
		return $count > 1;
	}
}














/* VALUES REPRESENTATIONS */

/*
* Return a PHP-like string representation of a scalar value.
*
* @param mixed $value The string, numeric or bool value to represent.
* @return string The string representation of the value.
*/
function scalar_to_string($val) {
	if($val === null)	return 'null';
	if(is_bool($val))	return ($val ? 'true' : 'false');
	if(is_string($val))	{
		// escape slashs & quotes
		$val = str_replace(array('\\', '\''), array('\\\\', '\\\''), $val);
		return '\''.$val.'\'';
	}
	return ''.$val;
}
/*
* Return a PHP-like string representation of any value, including arrays.
*
* @param mixed $value The value to represent.
* @param string $name Optionnal. If set, insert the value name before the value representation.
* @param bool $indent Optionnal. If set to True, represent the value with indents and line-breaks.
* @param bool $inside Optionnal. If set to True, use associative array operator between name and value.
* @return string
*/
function value_to_string($value, $name = false, $indent = false, $inside = false)
{
	if($indent) {
		$in = str_repeat("\t", $it);
		$sp = ' ';
		$nl = "\n";
		$str = $in;
	} else {
		$in = $sp = $nl = $str = '';
	}
	if($name !== false) {
		$del = $inside ? '=>' : '=';
		$str .= value_to_string($name) . $sp . $del . $sp;
	}
	$nbr = 0;
	if(is_array($value))
	{
		$str .= 'array(' . $nl;
		foreach($value as $key => $val)
		{
			if($nbr) $str .= ',' . $nl;
			$str .= value_to_string($val, $key, $indent, true);
			$nbr++;
		}
		$str .= $nl . $in . ')';
	}
	else $str .= scalar_to_string($value);
	return $str;
}
/*
 * Return the value interpretation of string(s).
 *
 * @param string|array $mixed The string or array to interpret.
 * @return string|bool|int|float The interpreted value.
 */
function string_to_value($mixed)
{
	if(is_array($mixed)) {
		foreach($mixed as $key => $val) {
			$mixed[$key] = string_to_value($val);
		}
	}
	elseif(is_string($mixed))
	{
		$str = rtrim($mixed);
		if(preg_match('`^null$`i', $str))			return null;
		if(preg_match('`^true$`i', $str))			return true;
		if(preg_match('`^false$`i', $str))			return false;
		if(preg_match('`^\d+$`', $str))				return intval($str);
		if(preg_match('`^\d+\.\d+$`', $str))		return floatval($str);
		else return $str;
	}
	return $mixed;
}
/*
 * Convert a string into a valable path.
 *
 * @param string $str The string to convert.
 * @return string The string converted.
 */
function string_to_path($str)
{
	$str = preg_replace('`(?:^\.?/|/\./|/$)`', '', trim($str));
	if(empty($str) || $str == '.') return '';
	return $str;
}
/*
 * Convert a value to a JSON string.
 * @param mixed $val The value
 * @return string The JSON string
 */
function value_to_json($val, $lvl = 0)
{
	if(is_string($val)) {
		$a = array('"', '\\', '/', "\b", "\f", "\n", "\r", "\t");
		$b = array('\"', '\\\\', '\/', '\\b', '\\f', '\\n', '\\r', '\\t');
		return '"' . str_replace($a, $b , $val) . '"';
	}
	elseif(!is_array($val)) return scalar_to_string($val);
	$tab = str_repeat("\t", $lvl);
	$tabs = str_repeat("\t", $lvl+1);
	$str = "\n" . $tab . "[\n";
	foreach($val as $k => $v)
	{
		$str .= $tabs . '"' . $k . '": ';
		$str .= value_to_json($v, $lvl+1);
		$str .= ",\n";
	}
	$str .= $tab . ']';
	return $str;
}
/*
 * Return an exerpt of a <i>str</i> maintaining words integrity.
 *
 * @param string $string The original string.
 * @param int $len The maximum number of characters of the excerpt.
 * @param string $end String added to the end if <i>str</i> is cut. By default "...".
 */
function excerpt($str, $len, $end = '...')
{
	if(strlen($str) > $len)
	{
		$str = mb_substr($str, 0, $len, 'UTF-8');
		$pos = max(strrpos($str, '.'), strrpos($str, ' '), strrpos($str, "\n"));
		if($pos) $str = substr($str, 0, $pos);
		$str .= $end;
	}
	return $str;
}
/*
 * Return a css #id compliant string.
 * Used for relying title id and anchor.
 */
function string_to_id($str)
{
	// remove tags
	$str = strip_tags($str);
	// replace any in-spaces by one underscore
	$str = preg_replace('`(?:\s|_)+`', '-', trim($str));
	// remove accents and delete special chars (based on their html code)
	$str = htmlentities($str, ENT_NOQUOTES, 'utf-8');
	$str = preg_replace('`&([A-za-z])(?:acute|cedil|circ|grave|orn|ring|slash|th|tilde|uml);`', '\1', $str);
	// delete anything else
	$str = preg_replace('`(?:&[^;]+;|[^a-zA-Z0-9-])`', '', $str);
	return $str;
}















/* TOOLS */

/*
 * Print the given array value, surrounded by <pre> tag for clarity.
 */
function preint($v)
{
	echo '<pre style="border:1px solid grey;padding:.5em;">';
	print_r(md_function('htmlentities', $v));
	echo '</pre>';
}

/*
 * Return a sorted version of a multi-dimentional
 * array. Preserve the keys/values association.
 *
 * @param array $arr the array to sort
 * @param string $col the array column base for sort
 * @param int $method Optionnal. The sort method, by default SORT_ASC.
 */
function md_multisort($arr, $col, $method = SORT_ASC)
{
	if(!is_array($arr) || empty($arr)) return false;
	if($col === null) return $arr;
	elseif($col == 'name') $tmp = array_keys($arr);
	else foreach($arr as $key => $row) $tmp[$key] = @$row[$col];
	array_multisort($tmp, $method, $arr);
	return $arr;
}
/*
 * Return a shuffle version of a multi-dimentional
 * array. Preserve the keys/values association.
* @return array
 */
function md_shuffle($arr)
{
	if(!is_array($arr)) return $arr;
	$keys = array_keys($arr);
	shuffle($keys);
	foreach ($keys as $key) $tmp[$key] = $arr[$key];
	return $tmp;
}
/*
 * Apply a function to any given value, even a multi-dimentional array.
 *
 * @param string $func the function name
 * @param mixed $arr the value
 * @param string $type verify the checking function is_[type] (is_bool, is_null...)
 * @return mixed
 */
function md_function($func, $arr, $type = null) {
	if(!is_array($arr)) {
		$check = 'is_' . $type;
		if(!$type || $check($arr)) return $func($arr);
		return $arr;
	}
	foreach ($arr as $k=>$v) $arr[$k] = md_function($func, $v, $type);
	return $arr;
}
/*
 * Flatten a multi-dimentional array by preserving any keys
 * and indicating the nested level .
 * @return array
 */
function md_flatten_keys($a, $lvl = 0){
	if(!is_array($a)) return $a;
	$b = array();
	foreach($a as $k => $v){
		$b[$k] = $lvl;
		if(is_array($v)) $b += md_flatten_keys($v, $lvl+1);
	}
	return $b;
}
/*
 * Attempts to remove the directory named by <i>dir</i> and its content.
 *
 * @see rmdir()
 * @param string $dir Path to the directory.
 * @return boolean Returns TRUE on success or FALSE on failure.
 */
function rmdir_r($dir)
{
	if(is_dir($dir)) {
		$objs = scandir($dir);
		foreach($objs as $o) {
			if($o != '.' && $o != '..') {
				if(is_dir($dir.'/'.$o)) {
					rmdir_r($dir.'/'.$o);
				} else unlink($dir.'/'.$o);
			}
		}
		reset($objs);
		return rmdir($dir);
	}
}
/*
 * Shift the first value of a <i>type</i> in the <i>array</i> off and returns it.
 *
 * @param array $a The input array.
 * @param string $t The type of value required (int|float|string|array).
 * @param bool $ct If set to true, check for "real" type of string. (only int)
 * @return mixed The required value, or false.
 * @see array_shift()
 */
function typed_array_shift(&$a, $t, $ct = false)
{
	if(!is_array($a)) return false;
	switch($t)
	{
		case 'string' :
			foreach($a as $k=>$v) if(is_string($v)){unset($a[$k]);return $v;}
		case 'int' :
			foreach($a as $k=>$v)
				if(is_int($v) || (is_string($v) && $ct && ctype_digit($v)))
				{unset($a[$k]);return intval($v);}
		case 'float' :
			foreach($a as $k=>$v) if(is_float($v)){unset($a[$k]);return $v;}
		case 'array' :
			foreach($a as $k=>$v) if(is_array($v)){unset($a[$k]);return $v;}
		case 'bool' :
			foreach($a as $k=>$v) if(is_bool($v)){unset($a[$k]);return $v;}
		case 'null' :
			foreach($a as $k=>$v) if(is_null($v)){unset($a[$k]);return $v;}
	}
	return false;
}
/*
 * Shift the first string matching <i>pattern</i> in
 * the <i>array</i> off and returns it.
 * If there is sub-matches, return the first one.
 *
 * @param array $array The input array.
 * @param string $pattern The pattern.
 * @return mixed The required value, or false.
 * @see array_shift()
 */
function preg_array_shift(&$array, $pattern)
{
	if(!is_array($array)) return false;
	foreach($array as $key => $value)
	{
		if(is_string($value) && preg_match($pattern, trim($value), $out))
		{
			unset($array[$key]);
			if(isset($out[1])) return $out[1];
			else return $value;
		}
	}
	return false;
}
/*
 * Return a random string.
 *
 * @param int $len The string length.
 * @param string $chr Optionnal. The characters used in the string. Default: a-zA-Z0-9
 * @return string	The random string
 */
function rand_string($len, $chr = null)
{
	if(!is_string($chr)) $chr = 'abcdefghijkmnopqrstuvwxyzABCDEFGHIJKLMNOPRSTUVWXYZ023456789';
	srand((double)microtime()*1000000);
	$str = '';
	for($i = 0; $i <= $len; $i++) {
		$num = rand() % 33;
		$str .= substr($chr, $num, 1);
	}
	return $str;
}







/*
 *	OUTPUT IMAGE
 */
if(!empty($_GET['image']))
{
	$w = !empty($_GET['w']) ? $_GET['w'] : null;
	$h = !empty($_GET['h']) ? $_GET['h'] : null;
	// redirect to the original if there is no resize to do
	if(!$w && !$h) header('Location: '. $_GET['image']);

	try {
		$image = new Image($_GET['image']);
		// redirect to the original if animated
		if($image->is_animated()) header('Location: '. $image->path);
		$image->load();
		$image->resize($w, $h, isset($_GET['crop']));
		$image->output();
	} catch (Exception $e) {}
	die();
}


if(!isset($config)) {
	$config = array(
		'site'=>array(
			'title'=>'Thiis',
			'description'=>'Thiis is default.'
		));
	file_put_contents(CONFIG, "<?php\n" .Thiis::value_to_string($config). "\n?>");
}
new Thiis($config);
?>
