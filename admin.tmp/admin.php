<?php
/*
 * There is thiis administration below.
 *
 * @package Thiis
 * @author Nicolas Liautaud <nicolas.liautaud@gmail.com>
 */
class Admin
{
	/*
	* CONSTRUCTOR
	*/
	function Admin($DATA)
	{

		// Rank constants
		foreach($this->config('rank') as $rank => $id)
			define(strtoupper($rank), $id);

		// POST format
		if(get_magic_quotes_gpc()) {
			$this->post = md_function('stripcslashes', $_POST, 'string');
		} else	$this->post = $_POST;


		// Update
		if(isset($this->get['update'])) $this->update();
		// GET message
		if(isset($this->get['updated'])) $this->admin_msg('updated', 'info', '', ADMINURL);
		if(isset($this->get['undo'])) $this->admin_msg('undo', 'info', '', REQURL);
		// Actions
		if(!empty($this->post['action'])) $this->actions();
	}


	/*
	* Handle unknow method call
	*/
	public function __call($name, $arguments)
	{
		if($this->visitor->rank)
			$this->infos .= $this->admin_msg('php', 'error',
				'<p>Call to undefined Thiis method :
					<b>'.$name.'('.implode(', ', $arguments).')</b>
				</p>');
	}


	/* SOURCE MANIPULATION */

	/*
	* Return actual file source content.
	* @return string
	*/
	function source() {
		return file_get_contents(FILENAME);
	}
	/*
	* Rewrite actual file source.
	* @param string $content
	*/
	function source_rewrite($content) {
		if($f = fopen(FILENAME, 'w+'))
			return fwrite($f, $content) && fclose($f);
		return false;
	}
	/*
	 * Return the actual core.
	 */
	function source_core() {
		$core_start = "\n/*\n * There is thiis below.";
		$src = $this->source();
		return substr($src, strpos($src, $core_start));
	}
	/*
	 * Rewrite a variable line in soure by an interpretation of its new value.
	 *
	 * @param string $type The data name.
	 * @param string $type Optionnal. If set, the data value will be replaced by this data.
	 * @return bool operation success
	 */
	function source_rewrite_var($data = null)
	{
		if($data === null) $data = $this->data;
		$data_str = value_to_string($data);
		$vars = "<?php\n\$DATA = " . $data_str . ";\n";
		return $this->source_rewrite($vars . $this->source_core());
	}
	/*
	* Redirect to a given url.
	*
	* @param string $url The url to go.
	* @param array $params Optionnal. Additional url parameters.
	*/
	function redirect($url, $params = null)
	{
		if($params) {
			if(is_array($params)) $params = implode('&', $params);
			$sep = strpos($url, '?') !== false ? '&' : '?';
		} else $sep = '';

		header('Location: ' . $url . $sep . $params);
		exit;
	}















	/* GETTERS / SETTERS */


	/*
	* Return the list of fields of data <i>type</i>.
	*
	* @param string $type The data type.
	* @return array The fields names.
	*/
	function get_fields($type)
	{
		if(!$this->exist($type) || empty($this->data[$type])) return array();
		$first = reset($this->data[$type]);
		if(empty($first) || !is_array($first)) return array();
		else return array_keys($first);
	}
	/*
	* Search values in data and return array of names.
	*
	* @param string $type The data type.
	* @param string $field Optionnal. The field filter (null for all).
	* @param string $value Optionnal. The value filter (null for all).
	* @param int $count Optionnal. The maximum number values to return.
	* @param int $method Optionnal. The sort method (null, SORT_ASC, SORT_DESC or other for shuffle).
	* @param string $sort Optionnal. Sort along this field. Don't sort if null.
	* @return array
	*/
	function find($type,
		$field = null, $value = null, $count = null,
		$method = null, $sort = null, $i18n = false
	) {
		$data = $this->get($type);
		if(!$data) return false;
		// sort
		if($method == SORT_ASC || $method == SORT_DESC)
			$data = $this->md_multisort($data, $sort, $method);
		elseif($method !== null)
			$data = $this->md_shuffle($data);

		// find
		$list = array();
		foreach($data as $name => $content)
		{
			if($i18n) {
				$lang = $this->language();
				$is_i18n = substr($name, -3, -2) == ':';
				if($is_i18n) {
					$is_good_i18n = substr($name, -2) == $lang;
					if(!$is_good_i18n) continue;
				} else {
					$exist_good_i18n = isset($data[$name . ':' . $lang]);
					if($exist_good_i18n) continue;
				}
			}

			if(($field === null && ($value === null || $value == $content))
			|| (is_array($content) && isset($content[$field]) && ($value === null || $content[$field] == $value)))
			{
				$list[] = $name;
				if($count === 1) return $name;
				elseif($count == count($list)) break;
			}
		}
		return $list;
	}
	/*
	 * Return a nested list of data names.
	 *
	 * @param string $type the data type
	 * @param string $parent the parent name
	 * @param int $lvl the current parenting level
	 * @return array
	 */
	function find_nested($type, $parent = '', $method = null, $sort = null, $max_lvl = null, $lvl = 0)
	{
		if($max_lvl && $lvl >= $max_lvl || $lvl > 999) return false;

		$childs = array_flip($this->find($type, 'parent', $parent, null, $method, $sort));
		if(empty($childs)) return false;

		foreach($childs as $name => $id) {
			$childs[$name] = $this->find_nested($type, $name, $method, $sort, $max_lvl, $lvl+1);
		}
		return $childs;
	}
	/*
	 * Return the item hierarchy as a parent chain.
	 *
	 * @param string $type the data type
	 * @param string $name the item name (optional, root by default)
	 * @return array parents items names
	 */
	function hierarchy($type, $name) {
		$hierarchy = array();
		$nbr = 0;
		while(!empty($name) && $nbr < 10) {
			$hierarchy[] = $name;
			$name = $this->get($type, $name, 'parent');
			$nbr++;
		}
		return array_reverse($hierarchy);
	}
	/*
	 * Get the nesting level between two data items.
	 * Positive: a is child of b. Negative: b is child of a.
	 *
	 * @param string $a the name of the first item
	 * @param string $b the name of the second item (optional, root by default)
	 * @return mixed the parenting level, or false
	 */
	function level($type, $a, $b = '')
	{
		$h = $this->hierarchy($type, $a);
		if(false !== ($a_id = array_search($a, $h))
		&& false !== ($b_id = array_search($b, $h)))
			return $a_id - $b_id;
		$h = $this->hierarchy($type, $b);
		if(false !== ($a_id = array_search($a, $h))
		&& false !== ($b_id = array_search($b, $h)))
			return $a_id - $b_id;
		return false;
	}



	/*
	 * Delete a value of designed data.
	 *
	 * @param string $type the data type
	 * @param string $name the value name
	 * @return bool operation success
	 */
	function del($type, $name)
	{
		if(!$this->exist($type, $name)) return false;

		unset($this->data[$type][$name]);
		return true;
	}
	/*
	 * Add a data or a value in designed data.
	 *
	 * @param string $type the data type
	 * @param string $item the item name (optionnal, add data type if null)
	 * @param string $content the data content
	 * @param int $id the position to place the new item (optionnal, queue by default)
	 * @return bool operation success
	 */
	function add($type, $item, $content, $id = null)
	{
		// add data type
		if($item === null)
			$this->data[$type] = $content;
		// add data item
		else {
			$insert = $id !== null && $id >= 0 && $id < count($this->data[$type]);
			if($insert) {
				$before = array_slice($this->data[$type], 0, $id);
				$after = array_slice($this->data[$type], $id);
				$this->data[$type] = $before + array($item => $content) + $after;
			}
			else $this->data[$type][$item] = $content;
		}
		return true;
	}
	/*
	 * Edit an item in designed data.
	 *
	 * @param string $type the data type
	 * @param string $item the item name
	 * @param array $data the new item data
	 * @return bool operation success
	 */
	function edit($type, $item, $data)
	{
		if(!$this->exist($type, $item)) return false;

		$this->data[$type][$item] = $data;
		return true;
	}
	/*
	 * Rename an item in designed data.
	 *
	 * @param string $type The data type.
	 * @param string $item The item name.
	 * @param string $newname The new item name.
	 * @return bool operation success
	 */
	function rename($type, $item, $newname)
	{
		return $this->exist($type, $item)
			&& $this->add($type, $newname, $this->data[$type][$item])
			&& $this->del($type, $item);
	}
	/*
	 * Swap two items in designed data <i>type</i>.
	 *
	 * @param string $type The data type.
	 * @param string $item1 The first item name.
	 * @param string $item2 The second item name.
	 * @param bool $rewrite Optionnal. If set to false the data are not rewrited in source.
	 * @return bool operation success
	 */
	function swap($type, $item1, $item2, $rewrite = true)
	{
		$t = DATAPREFIX . $type;
		global $$t;
		if(isset(${$t}[$item1]) && isset(${$t}[$item2]))
		{
			$new = array();
			foreach($$t as $item => $val)
			{
				if($item == $item1) $new[$item2] = ${$t}[$item2];
				elseif($item == $item2) $new[$item1] = ${$t}[$item1];
				else $new[$item] = $val;
			}
			${$t} = $new;
			if($rewrite) return $this->source_rewrite_var($type);
			return true;
		}
		return false;
	}
	/*
	 * Swap two fields in all items of data <i>type</i>.
	 *
	 * @param string $type The data type.
	 * @param string $name1 The first field name.
	 * @param string $name2 The second field name.
	 * @param bool $rewrite Optionnal. If set to false the data are not rewrited in source.
	 * @return bool operation success
	 */
	function swap_fields($type, $name1, $name2, $rewrite = true)
	{
		$t = DATAPREFIX . $type;
		global $$t;
		$first = reset($$t);
		if(isset($first[$name1]) && isset($first[$name2]))
		{
			foreach($$t as $item => $fields)
			{
				$new = array();
				foreach($fields as $name => $val)
				{
					if($name == $name1) $new[$name2] = ${$t}[$item][$name2];
					elseif($name == $name2) $new[$name1] = ${$t}[$item][$name1];
					else $new[$name] = $val;
				}
				${$t}[$item] = $new;
			}
			if($rewrite) return $this->source_rewrite_var($type);
			return true;
		}
		return false;
	}














	/* FILES */

	/*
	* Return a list of files and directories according to root.
	*
	* @param string $path Optionnal. Additional path relative to "files_path" setting.
	* @param string $type_filter Optionnal. If set, return only files with this type.
	* @return array The description of found files (name, type, preview) and directories (name, type, path).
	*/
	function file_get($path = '', $keep = null, $skip = null)
	{
		$files = array();
		$previewable = array('jpg','jpeg','gif','png');

		$path = $this->root($path);
		$handle = @opendir($path);

		if(!$handle) return false;

		while(false !== ($file = readdir($handle)))
		{
			$fullpath = $path . '/' . $file;
			// hide files/folders starting with a dot and thiis file.
			if($file[0] == '.' || $fullpath == FILENAME) continue;
			// file
			if(is_file($fullpath))
			{
				// determine type by extension
				$type = strtolower(pathinfo($file, PATHINFO_EXTENSION));
				if(in_array($type, $previewable)) $type = 'image';
				// skip according to types to keep and skip
				if(($skip == $type) || ($keep && $keep != $type)) continue;
				// create entry
				$files[$fullpath] = array('type' => $type);
				if($type == 'image') $files[$fullpath]['preview'] = $fullpath;
				if($data = $this->get('file', $fullpath)) $files[$fullpath] += $data;
			}
			// dir
			elseif(!$keep || $keep == '_dir') {
				$files[$fullpath] = array('type'=>'_dir');
			}
		}
		closedir($handle);
		ksort($files);
		return $files;
	}
	/*
	 * Upload a new file. (Look trough $_FILES) Relative to "files_path" setting.
	 *
	 * @param string $name The file name.
	 * @see actions()
	 */
	function file_add($name)
	{
		$file_tmp = $_FILES['data']['tmp_name']['file'];
		$file_ext = pathinfo($_FILES['data']['name']['file'], PATHINFO_EXTENSION);
		$file_size = filesize($file_tmp);

		// Check file autorisations (mime, type, ext)
		$unauthorized = false;
		$mime = $this->file_mime($file_tmp);
		$mime_pattern = '`[^;]\s*(?:'.$mime.'|'.dirname($mime).'|'.basename($mime).')`';
		if($blacklist = $this->setting('files_unauthorized'))
			$unauthorized = preg_match($mime_pattern, $blacklist);
		if($whitelist = $this->setting('files_authorized'))
			$unauthorized = !preg_match($mime_pattern, $whitelist);
		if($unauthorized) return $this->admin_msg('file_type', 'error');

		// Check max size
		if($max_size = $this->setting('files_max_size') && $file_size > $max_size) {
			return $this->admin_msg('file_size', 'error');
		}

		// Move
		if(!move_uploaded_file($file_tmp, $name)) {
			return $this->admin_msg('file_path', 'error');
		}

		return true;
	}
	/*
	 * Delete a file or a directory.
	 *
	 * @param string $path The target path.
	 */
	function file_del($path) {
		if(is_file($path)) return unlink($path);
		if(is_dir($path)) return $this->rmdir_r($path);
		return false;
	}
	/*
	 * Rename a file or a directory.
	 *
	 * @param string $oldpath The target path.
	 * @param string $newpath The new target path.
	 */
	function file_rename($oldpath, $newpath) {
		if($oldpath != $newpath && file_exists($oldpath))
			return rename($oldpath, $newpath);
		return false;
	}
	/*
	* Try to return the mime content type of the file located in <i>filepath</i>.
	*
	* @param string $filepath The file path.
	* @return string The file mime type.
	*/
	function file_mime($filepath)
	{
		// (PHP >= 5.3.0)
		if(class_exists('finfo')) {
			$fi = new finfo(FILEINFO_MIME);
			return $fi->buffer(file_get_contents($filepath));
		}
		// (PHP >= 4.3.0)
		elseif(function_exists('mime_content_type')) {
			return mime_content_type($filepath);
		}
		// Linux server
		elseif(PHP_OS == 'Linux'){
			$mime = exec('file --brief --mime-type ' . escapeshellcmd($filepath) . ' 2>/dev/null');
		   if(substr($mime, 0, 5) != 'ERROR') return $mime;
		}
		// Fail
		return false;
	}




















	/* URL REWRITING */

	function edit_htaccess()
	{
		$htaccess_path = './.htaccess';
		$use_fancy_url = $this->setting('use_fancy_url');

		$thiis_start	= "\n\n#\n# Thiis\n#\n";
		$thiis_start .= "RewriteEngine on\n";
		$thiis_start .= "Options -Indexes -MultiViews +FollowSymLinks";
		$thiis_end = "\n\n#\n# End thiis\n#";

		$file_rule	= "\n\n# Thiis file " . FILENAME;
		if(FILENAMEBASE == 'index') {
			$file_rule .= "\n" . 'RewriteCond %{REQUEST_FILENAME} !-f';
			$file_rule .= "\n" . 'RewriteCond %{REQUEST_FILENAME} !-d';
			$file_rule .= "\n" . 'RewriteRule ^(' . NAMECHAR . '*)$	';
			$file_rule .= FILEPATH . '/index.php?page=$1	[QSA,L]';
		}
		else{
			$file_rule .= "\n" . 'RewriteRule ^' . FILENAMEBASE . '(?:/(' . NAMECHAR . '*))?$	';
			$file_rule .= FILEPATH . FILENAME . '?page=$1	[QSA,L]';
		}

		// There is no .htaccess file and we want : create the file.
		if(!file_exists($htaccess_path) && $use_fancy_url)
		{
			$content = $thiis_start . $file_rule . $thiis_end;
		}
		elseif(file_exists($htaccess_path)
		&& ($content = file_get_contents($htaccess_path)) !== false)
		{
			$is_rule = preg_match('`\Q' . $file_rule . '\E`', $content);

			// Add rules
			if($use_fancy_url)
			{
				if(!preg_match('`\Q' . $thiis_start . '\E`', $content)) {
					$content .= $thiis_start . $thiis_end;
				}
				if(!$is_rule) {
					// Escape backreferences
					$file_rule = addcslashes($file_rule, '\\$');
					// Always place index rule at the end
					if(FILENAMEBASE == 'index') {
						$content = preg_replace('`\Q' . $thiis_end . '\E`', $file_rule . $thiis_end, $content);
					}
					else $content = preg_replace('`\Q' . $thiis_start . '\E`', $thiis_start . $file_rule, $content);
				}
			}
			// Clean
			else {
				// Delete file rule
				if($is_rule) {
					$content = preg_replace('`\Q' . $file_rule . '\E`', '', $content);
				}
				// Delete Thiis part if there is no file rules
				if(substr_count($content, '# Thiis file') == 0) {
					$content = preg_replace('`\Q' . $thiis_start . '\E`', '', $content);
					$content = preg_replace('`\Q' . $thiis_end . '\E`', '', $content);
				}
				// Delete the file if there is nothing else
				if(trim($content) == '') {
					unlink($htaccess_path);
					unset($content);
				}
			}
		}
		// Write file
		if(isset($content))
		{
			$file = fopen($htaccess_path, 'w+');
			fwrite($file, $content);
			fclose($file);
		}
	}

	/* VERSIONING */

	/*
	 * Return the version of another thiis.
	 *
	 * @param string $param $url Optionnal. If not set, use the last official version.
	 * @return int|bool The found version or false
	 */
	function version($url = null)
	{
		if($url == null && !($url = $this->setting('update_url'))) $url = THIISURL;
		if($url == SITEURL) return $this->version;
		$h = $this->get_headers($url, 1);
		if(!empty($h['thiis-version'])) return intval($h['thiis-version']);
		return false;
	}
	/*
	 * Return the HTML headers of a given url. A php4 working version of get_headers().
	 *
	 * @param string $param $url
	 * @param int $param $format
	 * @return array
	 */
	function get_headers($url, $format=0)
	{
		// format url
		$url = parse_url($url);
		if(!isset($url["scheme"])){
			$url["host"] = $url["path"];
			$url["path"] = '';
		}
		$s_Host = $url['host'];
		if(empty($url['path'])) $url['path'] = '/?';
		$s_URI = $url['path'];
		if(!empty($url['query'])) $s_URI .= '?' . $url['query'];
		$s_Port = !empty($url['port']) ? $url['port'] : 80;
		// connexion
		if($fp = @fsockopen($s_Host, $s_Port, $errno, $errstr, 2))
		{
			$header = '';
			$request	= "GET $s_URI HTTP/1.0\r\n";
			$request .= "Host: $s_Host\r\n";
			$request .= "Connection: Close\r\n\r\n";
			// make/get request
			fputs($fp, $request);
			while(!feof($fp)) {
				$header .= fgets($fp, 4096);
				if(strpos($header,"\r\n\r\n")) break;
			}
			fclose($fp);
			// format output
			$header = preg_replace("/\r\n\r\n.*\$/",'',$header);
			$header = explode("\r\n", $header);
			if($format) {
				foreach($header as $i)
					if(preg_match('/^([a-zA-Z -]+): +(.*)$/', $i, $o))
						$v[$o[1]] = $o[2];
				return $v;
			}
			return $header;
		}
		return false;
	}
	/*
	 * Update thiis core from another thiis.
	 *
	 * @param string $param $dist_url Optionnal. If not set, use the setting "update_url" or the official version.
	 */
	function update($dist_url = null)
	{
		// check rights
		if($this->visitor->rank < ADMIN) {
			return $this->admin_msg('right', 'error', '', SITEURL);
		}
		// define distant url
		if($dist_url == null && !($dist_url = $this->setting('update_url')))
			$dist_url = THIISURL;

		// check distant headers
		$dist_headers = get_headers($dist_url, 1);
		if(!isset($dist_headers['thiis-version'])) return false;

		// download
		$dist_src = file_get_contents($dist_url . '?download');

		// rewrite
		$this->source_rewrite($dist_src);
		$this->source_rewrite_var($this->data);
		$this->redirect(ADMINURL, 'updated');
	}



















	/* ACTIONS */

	/*
	 * Check for actions, manage global errors and execute it.
	 */
	function actions()
	{
		$action = $this->post['action'];
		$field = isset($this->post['field']);
		$newname = trim(strtolower(str_replace(' ','_', $this->post['newname'])));
		$name = !empty($this->post['name']) ? $this->post['name'] : $newname;
		$data_type = $this->post['data_type'];
		$old_data = $this->get($data_type);
		$data = isset($this->post['data']) ? $this->post['data'] : null;

		// Bases actions and true = undo message, false = confirm message, null = no message
		$actions = array(
			'add'=>true, 'edit'=>true, 'preview'=>false, 'del'=>true,
			'undo'=>false, 'arrange'=>null, 'filter'=>null);

		// Retrieve the good action name from translated one
		foreach($actions as $a => $u) {
			if($this->word($a) == $action) {
				$action = $a;
				$action_message = $u;
				break;
			}
		}
		//filter is a silent edit
		if($action == 'filter') $action = 'edit';

		// right error
		if(!$this->is_granted($action)
		|| !$this->is_granted($data_type)) {
			return $this->admin_msg('right', 'error');
		}
		// Naming
		if(empty($newname)) {
			// name from file
			if($data_type == 'file') {
				if(!empty($_FILES['data']['tmp_name']['file']))
					$newname = $name = trim(strtolower(str_replace(' ','_', $_FILES['data']['name']['file'])));
				else return;
			}
			// automatic name
			else $newname = $name = $this->vacant_name($data_type);
		}
		else {
			// name validity
			if(!preg_match('`^'.NAMECHAR.'+(:[a-z]{2})?$`', $newname))
				return $this->admin_msg('name_format', 'error');
			// name reserved
			if($this->is_reserved_name($newname))
				return $this->admin_msg('name_reserved', 'error');
			// name exists
			if($action == 'add' && $this->get($data_type, $newname))
				return $this->admin_msg('name_exist', 'error');
		}
		// Call the action method
		$action_method	= 'action_' . ($field ? 'field_' : '') . $action;
		if(method_exists('Thiis', $action_method))
		{
			if(is_array($data))
			{
				// Data may be a single value
				if(count($data) == 1) $data = reset($data);

				// Default date
				elseif(isset($data['date']) && empty($date))
					$data['date'] = date('Y-m-d');
			}
			// files : use full path and manage directory actions
			if($data_type == 'file' && !$field)
			{
				$path = $this->root($this->get['path']);
				if(isset($this->get['add_dir'])) return mkdir($path . '/' . $newname);
				elseif(isset($this->get['edit_dir'])) {
					if($action == 'edit') return $this->file_rename($path, dirname($path) . '/' . $newname);
					if($action == 'del') return $this->file_del($path);
				}
				$name = $path . '/' . $name;
				$newname = $path . '/' . $newname;
			}
			// manage cache
			elseif($data_type == 'page' && !empty($data['cache']) && $action != 'undo') {
				$content = $this->parse($data['content'], true);
				$data['cache'] = base64_encode(gzdeflate($content));
			}
			// do action on data
			if($result = $this->$action_method($data_type, $name, $data))
			{
				// additional actions on files / upload
				if(!empty($_FILES['data']['tmp_name']['file'])) {
					if($action == 'edit') $this->file_del($name);
					$this->file_add($newname);
				}
				// rename
				if($action == 'edit' && $newname != $name) {
					if($data_type == 'file') $this->file_rename($name, $newname);
					$this->rename($data_type, $name, $newname);
					$this->source_rewrite_var();
				}
				// Display an info message
				if($data_type == 'file' || $action_message === false) {
					$button = $action == 'preview' ? 'undo' : 'ok';
					$this->admin_msg($action, 'info');
				}
				// Display a undo message
				elseif($action_message === true) {
					$this->admin_msg_undo($action, $data_type, $old_data);
				}
				// Action modifying .htaccess
				if($data_type == 'setting' && $newname == 'use_fancy_url') {
					$this->edit_htaccess();
				}
			}
			elseif($result === false) $this->admin_msg($action, 'error');
		}
	}
	/*
	 * Add a new item into a data.
	 *
	 * @param string $type The data type.
	 * @param string $name The new item name.
	 * @param array $data The item data.
	 * @see actions()
	 */
	function action_add($type, $name, $data)
	{
		// Generate or encrypt password if needed
		if(isset($data['password'])) {
			if(!strlen(trim($data['password']))) {
				$data['password'] = sha1($this->rand_string(7));
			} else $data['password'] = sha1(trim($data['password']));
		}
		if(!$type) $this->add('data', $name, array());
		return $this->add($type, $name, $data) &&  $this->source_rewrite_var();
	}
	/*
	 * Edit a data item.
	 *
	 * @param string $type The data type.
	 * @param string $name The item name.
	 * @param array $data The new item content.
	 * @see actions()
	 */
	function action_edit($type, $name, $data)
	{
		// Do not change password if empty
		if(is_array($data) && isset($data['password'])) {
			if(!strlen(trim($data['password']))) {
				$data['password'] = $this->get($type, $name, 'password');
			} else $data['password'] = sha1(trim($data['password']));
		}
		return ($this->edit($type, $name, $data) || $this->add($type, $name, $data))
		&& $this->source_rewrite_var();
	}
	/*
	 * Delete a data item.
	 *
	 * @param string $type The data type.
	 * @param string $name The item name.
	 * @param array $data The item content.
	 * @see actions()
	 */
	function action_del($type, $name, $data)
	{
		// nothing to do if the item do not exist
		if(!$this->get($type, $name)) return true;

		// prevent deleting last data
		if(!count(array_diff($this->find($type), array($name)))) {
			return $this->admin_msg('last_data', 'error');
		}
		// prevent deleting last admin
		if($type == 'user'
		&& $this->get('user', $name, 'rank') == ADMIN
		&& count($this->find('user', 'rank', ADMIN)) < 2) {
			return $this->admin_msg('last_admin', 'error');
		}
		// delete
		$success = $this->del($type, $name) && $this->source_rewrite_var();
		if($type == 'file') $success &= $this->file_del($name);

		// Logout at self-delete
		if($type == 'user' && $name == $this->visitor->name) $this->logout();

		return $success;
	}
	/*
	 * Preview a data item. Apply changes without rewriting.
	 *
	 * @param string $type The data type.
	 * @param string $name The item name.
	 * @param array $data The new item content.
	 * @see actions()
	 */
	function action_preview($type, $name, $data)
	{
		return $this->edit($type, $name, $data);
	}
	/*
	 * Replace a data by the given <i>data_str</i>
	 *
	 * @param string $type The data type
	 * @param string $name The item name
	 * @param string $data The item data (serialized and base64 encoded)
	 * @see actions()
	 */
	function action_undo($type, $name, $data)
	{
		$data = base64_decode($data);
		$data = unserialize($data);
		$this->source_rewrite_var($type, $data);
		$this->redirect(REQURL, 'undo');
	}
	/*
	 * Place a data item after another one, and set parent.
	 *
	 * @param string $type The data type.
	 * @see actions()
	 */
	function action_arrange($type) {
		$new_parent = $this->post['parent'];
		$source_name = $this->post['source'];
		$target_name = $this->post['target'];

		$target_id = array_search($target_name, $this->find($type));
		$source = $this->get($type, $source_name);
		$source['parent'] = $new_parent;

		return 	$this->del($type, $source_name)
		&&		$this->add($type, $source_name, $source, $target_id)
		&&		$this->source_rewrite_var();
	}
	/*
	 * Add a new data field.
	 *
	 * @param string $type The data type.
	 * @param string $field The new field name.
	 * @param array $default The default field value.
	 * @see actions()
	 */
	function action_field_add($type, $field, $default)
	{
		if($data = $this->get($type)){
			foreach($data as $item => $val)
			{
				if(!is_array($val)) $val = array('value'=>$val);
				$val[$field] = $default;
				$this->edit($type, $item, $val);
			}
			return $this->source_rewrite_var($type);
		}
		return false;
	}
	/*
	 * Delete a data field.
	 *
	 * @param string $type The data type.
	 * @param string $field The field name.
	 * @see actions()
	 */
	function action_field_del($type, $field)
	{
		if(in_array($field, $this->get_fields($type)))
		{
			$data = $this->get($type);
			foreach($data as $item => $val)
			{
				unset($val[$field]);
				if(count($val) == 1){
					$val = reset($val);
				}
				$this->edit($type, $item, $val);
			}
			return $this->source_rewrite_var($type);
		}
		return false;
	}
	/*
	 * Swap a data field with the previous data field.
	 *
	 * @param string $type The data type.
	 * @param string $name The field name.
	 * @see actions()
	 */
	function action_field_up($type, $name, $data)
	{
		$fields = $this->get_fields($type);
		return $this->swap_fields($type, $name, $fields[array_search($name, $fields) - 1]);
	}
	/*
	 * Swap a data field with the next data field.
	 *
	 * @param string $type The data type.
	 * @param string $name The field name.
	 * @see actions()
	 */
	function action_field_down($type, $name, $data)
	{
		$fields = $this->get_fields($type);
		return $this->swap_fields($type, $name, $fields[array_search($name, $fields) + 1]);
	}























	/*
	 * Return the current files path.
	 */
	function path()
	{
		$path = isset($this->get['path']) ? $this->root($this->get['path']) : '';
		while(!is_dir($path)) $path = $this->root(dirname($path));
		return $path;
	}
	/*
	 * Return the root path based on setting.
	 *
	 * @param string $relpath Optionnal. And additionnal relative path.
	 * @return string The path.
	 */
	function root($relpath = '')
	{
		$relpath = $this->string_to_path($relpath);
		$root = $this->string_to_path($this->setting('files_path'));
		$root = $this->string_to_path($root . '/' . $relpath);
		if(empty($root)) $root = '.';
		return $root;
	}
	/*
	 * Return the absolute root url based on setting.
	 *
	 * @param string $relpath Optionnal. And additionnal relative path.
	 * @return string The path.
	 */
	function absroot($path = '') {
		return SERVERURL . FILEPATH . '/' . $this->root($path);
	}
	function absurl($url)
	{
		// option
		if($url[0] == '?') return $this->absroot() . $url;
		// file
		if(is_file($url = $this->root($url))) return $url;
		// external url / mailto
		if(preg_match('`^'.URLPATTERN.'$`', $url, $out)) {
			if(!empty($out[2])) return 'mailto:' . $out[0];
			elseif(empty($out[1])) return 'http://' . $url;
			return $url;
		}
		// page
		elseif(preg_match(PAGEPATTERN, $url, $out))
		{
			if(!isset($out[2])) $out[2] = '';
			return $this->page_url($out[1], $out[2]);
		}
		return false;
	}



















	/* LANGUAGE */

	/*
	 * Return the most appropriate language according to existing core translations
	 * and GET, SESSION, site setting and browser setting. If all failed, use "en".
	 *
	 * @return string The language code.
	 */
	function language()
	{
		if(!empty($this->get['lang'])
		&& isset($this->translations[$this->get['lang']])) return $this->get['lang'];

		if(!empty($_SESSION['lang'])
		&& isset($this->translations[$_SESSION['lang']])) return $_SESSION['lang'];

		if(!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])
		&& ($browser_lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2))
		&& isset($this->translations[$browser_lang])) return $browser_lang;

		if($setting_lang = $this->setting('language')
		&& isset($this->translations[$setting_lang])) return $setting_lang;

		return 'en';
	}
	/* Alias for syntax output */
	function html_language() {
		return $this->language();
	}
	/*
	 * Check if a given item name is a i18n format, or return a
	 * i18n format version of the name for a given lang.
	 * @param string $name the data item name
	 * @param string $name the lang code (en, fr...)
	 * @return mixed lang code/false or i18n name format
	 */
	function i18n_lang($name, $lang = null)
	{
		$is_i18n = substr($name, -3, -2) == ':';
		if($lang === null) {
			if($is_i18n) return substr($name, -2);
			else return false;
		} else {
			if($is_i18n) return substr($name, 0, -2) . $lang;
			else return $name . ':' . $lang;
		}
	}



















	/*
	 * Return styles of given html with every css encapsulated in given encapsulation id.
	 * @return string
	 */
	function css($html, $id)
	{
		$output = '';
		$css_pattern = '`(?<=<!--|}|\);)\s*([^@{}]+{)`';
		$css_func = '
			$out = "";
			foreach(explode(",", $in[1]) as $i => $v)
				$out .= ($i?", ":"\n") . "#'.$id.' " . $v;
			return $out;';
		preg_match_all('`(<style[^>]*>\s*)(.*)(\s*</style>)`sU', $html, $styles, PREG_SET_ORDER);
		foreach($styles as $style) {
			list(,$open, $content, $close) = $style;
			$content = preg_replace_callback($css_pattern, create_function('$in', $css_func), $content);
			$content = preg_replace('`(#'.$id.'[^{]*)(body|html)`sU', '\\1#\\2' ,$content);
			$output .= $open . $content . $close;
		}
		return $output;
	}
	/*
	 * Return the administration menu
	 */
	function menu()
	{
		$data = $this->find('data');
		$data[] = 'data';
		$html = '<ul>';
		foreach($data as $type) {
			if($this->is_granted($type)) {
				$html .= '<li class="'.$type;
				if($this->current() == $type) $html .= ' selected">';
				else $html .= '">';
				$html .= $this->link($type);
				$html .= '</li>';
			}
		}
		$html .= '</ul>';
		return $html;
	}
	/*
	 * Return the name of the admin page to display,
	 * according to rights and existing ones.
	 */
	function current() {
		$type = $this->get['admin'];

		if($type == 'data' && $this->visitor->rank >= ADMIN)
			return 'data';

		$datas = $this->find('data');
		if(!in_array($type, $datas)) $type = reset($datas);
		if(!$this->is_granted($type)) return false;
		return $type;
	}
	/*
	 * Return an html link to a specific administration page.
	 *
	 * @param string $name Optionnal. Admin page name. If not set, link to admin index page.
	 * @param string $params Optionnal. Additionnal parameters. (example : add)
	 * @param string $text Optionnal. If set use this as link text.
	 */
	function link($name = '', $params = null, $text = null, $class = '')
	{
		if($text === null) {
			if(!$this->setting('use_page_names')) {
				if(empty($name)) $text = word('administration');
				else $text = word($name);
			}
			else $text = ucfirst($name);
		}

		$href = ADMINURL;
		if(!empty($name)) $href .= '=' . $name;
		if(!isset($params['path']) && !empty($this->get['path'])) $params['path'] = $this->get['path'];
		if(!isset($params['page'])) $params['page'] = PAGENAME;
		foreach($params as $k => $v) {
			$href .= '&amp;';
			if(is_string($k)) $href .= $k . '=' . $v;
			else $href .= $v;
		}
		return '<a href="' . $href . '" class="' . $class . '">' . $text . '</a>';
	}
	/*
	 * Return the management page.
	 */
	function management()
	{
		// TODO admin management

		$type = 'data';
		$data = $this->find($type);
		$name = !empty($this->get['edit']) ? $this->get['edit'] : '';

		// Update
		$other = $this->version();
		if($other > $this->version) {
			$this->msg(
				'update', 'info',
				'<a href="'.SITEURL.'?update" class="btn">' . word('update_to') . ' ' . date('y.m.d', $other) . '</a>',
				false);
		}

		// Add
		$html = $this->form_add('');
		$html .= $this->list('data');

		return $html;
	}
	/*
	 * Return the management page of data <i>type</i>.
	 */
	function management_data($type)
	{
		$data = $this->find($type);
		$name = !empty($this->get['edit']) ? $this->get['edit'] : '';
		$html = '';

		// Add
		if(isset($this->get['add']))
			return $this->form_add($type);

		if($type) {
			// Edit fields
			if(isset($this->get['edit_fields'])) {
				$html .= $this->form_add($type, true);
				$html .= $this->list_fields($type);
			}
			// Edit
			if(!empty($name) && in_array($name, $data)) {
				$html .= $this->form_edit($type, $name);
			}
			// content
			else $html .= $this->list($type);
		}

		return $html;
	}
	/*
	 * Return the management page of files.
	 */
	function management_file()
	{
		$path = isset($this->get['path']) ? $this->get['path'] : '';
		while(!empty($path) && !file_exists($this->root($path))) {
			$path = $this->string_to_path(dirname($path));
			$this->string_to_path($p);
		}

		$html = '';
		// Add directory
		if(isset($this->get['add_dir'])) {
			$html .=	'<b>' . word('dir_add') .	'</b>';
			$html .= '<form method="post" action="'.REQURL.'">';
			$html .= $this->form_fields('file', '', null);
			$html .= '<div class="buttons">';
			$html .= '<input type="hidden" name="data_type" value="file" />';
			$html .= '<input type="submit" class="btn" name="action" value="' . word('add') . '" />';
			$html .= '</div></form>';
		}

		// Edit directory
		if(isset($this->get['edit_dir'])
		&& $path && $path !='.') {
			$html .= '<br /><b>' . word('dir_edit') .	'</b>';
			$html .= '<form method="post" action="' . REQURL . '">';
			$html .= $this->form_fields('file', basename($path), null);
			$html .= '<div class="buttons">';
			$html .= '<input type="hidden" name="data_type" value="file" />';
			$html .= '<input type="submit" class="btn" name="action" value="' . word('edit') . '" />';
			$html .= '<input type="submit" class="btn" name="action" value="' . word('del') . '" />';
			$html .= '</div></form>';
		}

		// Add file
		if(isset($this->get['add'])) $html .= $this->form_add('file');

		// Field edit
		if(isset($this->get['edit_fields'])) {
			$html .= $this->form_add('file', true);
			$html .= $this->list_fields('file');
		}

		// Edit file
		if(isset($this->get['edit']) && is_file($this->root($path) . '/' . $this->get['edit']))
		{
			$name = $this->get['edit'];
			$html .= $this->form_edit('file', $name);
		}
		// browser
		else {
			$html .= $this->list('file');
			$html .= $this->link('file', array('path'=>$path, 'add_dir'), word('dir_add'), 'btn add');
			if($path && $path !='.')
				$html .= $this->link('file', array('path'=>$path, 'edit_dir'), word('dir_edit'), 'btn edit');
		}

		return $html;
	}

	/*
	 * Return data list
	 */
	function list($type)
	{
		$sort = !empty($this->get['sort']) ? $this->get['sort'] : ($type == 'file' ? 'type' : null);
		$method = (!empty($this->get['method']) && $this->get['method'] == 'desc') ? SORT_DESC : SORT_ASC;

		// define existing/filtered fields
		$existing_fields = $this->get_fields($type);
		$display_fields = $this->get('data', $type);
		if(!is_array($display_fields)) $display_fields = array($display_fields);

		if($display_fields) $fields = array_intersect($existing_fields, $display_fields);
		elseif($type == 'file') $fields = array('type', 'preview');
		elseif(!$existing_fields) $fields = array('value');
		else $fields = array();
		array_unshift($fields, 'name');
		// fields header
		$html_fields = '';
		foreach($fields as $field) {
			$fieldname = word($field);
			if($sort == $field) {
				if($method == SORT_ASC)
					$html_fields .= $this->link(
						$type, array('sort'=>$field, 'method'=>'desc'),
						$fieldname . ' ▲');
				else
					$html_fields .= $this->link(
						$type, array('sort'=>$field, 'method'=>'asc'),
						$fieldname . ' ▼');
			}
			else $html_fields .= $this->link($type, array('sort'=>$field), $fieldname);
		}

		// fields filtering form
		if($existing_fields) {
			$fields_selection .= '<form id="columnsFilter" method="post" action="' . REQURL . '">';
			$fields_selection .= '<input type="hidden" name="data_type" value="data" />';
			$fields_selection .= '<input type="hidden" name="newname" value="'.$type.'" />';
			$fields_selection .= '<input type="submit" class="btn" name="action" value="' . word('filter') . '" />';
			foreach($existing_fields as $field) {
				$fieldname = word($field);
				$fields_selection .= '<label class="label"><input type="checkbox" name="data[]" value="'.$field.'"';
				if(array_search($field, $display_fields) !== false) $fields_selection .= ' checked';
				$fields_selection .= ' />'.$fieldname.'</label>';
			}
			$fields_selection .= '</form>';
		}
		$fields_selection .= $this->link($type, array('edit_fields'), word('edit'), 'btn add');

		$html = '<div class="listhead">' . $html_fields . $fields_selection . '</div>';
		$html.= $this->list_items($type, $fields, $sort, $method);
		$html .= $this->link($type, array('add'), word('add_one'), 'btn add');
		return $html;
	}
	/*
	 * Return data list items
	 */
	function list_items($type, $fields, $sort = null, $method = SORT_DESC, $parent = false)
	{
		$html = '<ul class="list">';
		// files
		if($type == 'file') {
			$path = $this->path();
			// get and sort
			$files = $this->file_get($path);
			$files = $this->md_multisort($files, $sort, $method);
			$childs = array_keys($files);
			// parent directory
			$dir_icon = '<img class="icon" src="data:image/gif;base64,R0lGODlhDQALAMQAAOvGUf7ztuvPMf/78/fkl/Pbg+u8Rvjqteu2Pf3zxPz36Pz0z+vTmPzurPvuw/npofbjquvNefHVduuyN+uuMu3Oafbgjfnqvf/3zv/3xevPi+vRjP/20/bmsP///////yH5BAEAAB8ALAAAAAANAAsAAAVaoMcwWhR5aCp6WJtVACDMAsO6WR4EmbANLoBhiEBMKLGkYulwHBLIgVTRaDwIBIsDydlVr5ZC4YBcVK3ZgkTSoRgS37SkUoG4E4tFwnE5dCAQF25FhBOGhxQhADs=" alt="dir" />';
			if($path != $this->root()) {
			   $html .= '<li class="item dir">';
				$name = '<b>' . $dir_icon . '◄ ' . word('parent_dir') . ' : ' . basename(dirname($path)) . '</b>';
				$html .= $this->link('file', array('path'=>dirname($path)), $name);
				$html .= '</li>';
			}
		}
		// data
		else {
			$childs = $this->find($type, 'parent', $parent, null, $method, $sort);
			if($parent == false && empty($childs)) $childs = $this->find($type);
		}
		if(!$childs) return;

		// list data/files
		foreach($childs as $id => $name)
		{
			// skip translated item (manage versions in original item)
			if($this->i18n_lang($name)) continue;

			if($type == 'file') {
				$data = $files[$name];
				$childs_nbr = 0;
				$is_current = $is_parent = false;
				// directory
				if($data['type'] == '_dir') {
					$html .= '<li class="item dir">';
					$html .= $this->link('file', array('path'=>$name), '<b>' . $dir_icon . basename($name) . '</b>');
					$html .= '</li>';
					continue;
				}
			} else {
				$data = $this->get($type, $name);
				$childs_nbr = count($this->find($type, 'parent', $name));
				if($type == 'page') {
					$level = $this->level($type, PAGENAME, $name);
					$is_current = $level === 0;
					$is_parent = $level > 0;
					$is_child = $level < 0;
				} else {
					$is_current = false;
					$is_parent = false;
					$is_child = true;
				}
			}

			// Adapt non-array values and add name data
			if(!is_array($data)) $data = array('value' => $data);
			else $data = array_intersect_key($data, array_flip($fields));
			$data = array('name' => $name) + $data;

			// Item columns
			$item_content = '';
			foreach($data as $key => $val)
			{
				if(($type == 'right' && $key == 'value')
						|| $key == 'rank')		$val = word($this->find('rank', null, $val, 1));
				elseif($key == 'name')			$val = basename($val);
				elseif($key == 'preview')		$val = $this->html_image($name, false, '200');
				elseif($key == 'cache')			$val = !empty($val);
				elseif(is_string($val))			$val = $this->excerpt($this->markup_del($val, false), 30);
				elseif($val === true)			$val = word('yes');
				elseif($val === false)			$val = word('no');

				$item_content .= '<span class="column '.$key.'">' . $val . '</span>';
			}

			// Item link
			$params = array();
			if($type == 'page') $params['page'] = $name;
			if($is_current) $params['edit'] = basename($name);
			if(!empty($path)) $params['path'] = $path;

			$item_link = $this->link($type, $params, $item_content);

			// Item translations
			$item_translations = '';
			$i=0;
			do {
				if($n = $this->preg_array_shift($childs, '`^(' .$name.':[a-z]{2})$`')) {
					$item_translations .= $this->link($type, array('edit'=>$n, 'page'=>$n), $this->i18n_lang($n), 'lang');
					$i++;
				}
			} while ($n);
			if($item_translations) $item_translations = "<div class='i18n'>$item_translations</div>";

			// Item childs
			$item_childs = $this->list_items($type, $fields, $sort, $method, $name);

			$item_class = $is_current ? ' current' : ($is_parent ? ' parent' : '');
			$html .= "
				<li class='item$item_class' draggable='true'>
					$item_link
					$item_translations
					$item_childs
				</li>";
		}
		$html .= '</ul>';
		return $html;

	}
	/*
	 * Return fields list
	 */
	function list_fields($type)
	{
		$html = '<h4>' . word('fields_list') . '</h4><ul class="fieldslist">';
		$fields = $this->get_fields($type);
		if(!$fields) return $html .= '<li class="locked"><div class="box">value</div></li></ul>';
		foreach($fields as $field)
		{
			if($this->is_reserved_name($field))
				$html .= '<li class="locked"><div class="box">' . $field . '</div>';
			else {
				$html .= '<li><div class="box">' . $field . '</div>';
				$html .= '<form method="post" class="inline" action="'.REQURL.'">';
				$html .= '<input type="submit" name="action" class="del" value="' . word('del') . '" />';
				$html .= '<input type="submit" name="action" class="up" value="' . word('up') . '" />';
				$html .= '<input type="submit" name="action" class="down" value="' . word('down') . '" />';
				$html .= '<input type="hidden" name="data_type" value="' . $type . '" />';
				$html .= '<input type="hidden" name="field" value="true" />';
				$html .= '<input type="hidden" name="newname" value="' . $field . '" />';
				$html .= '</form>';
			}
			$html .= '</li>';
		}
		$html .= '</ul>';
		return $html;
	}
	/*
	 * Return login form.
	 */
	function form_login($gotoreferer = true)
	{
		if($gotoreferer && isset($_SERVER['HTTP_REFERER'])) $goto = $_SERVER['HTTP_REFERER'];
		else $goto = REQURL;
		return '
		<form id="loginForm" method="post" action="'.REQURL.'" >
			<input type="hidden" name="goto" value="' . $goto . '" />
			<label class="field name">
				<div class="label">' . word('name') . '</div>
				<input type="text" name="login" class="input" />
			</label>
			<label class="field password">
				<div class="label">' . word('password') . '</div>
				<input type="password" name="password" class="input" />
			</label>
			<div class="buttons">
				<input type="submit" class="btn" value="' . word('login') . '" />
				<a href="' . $goto . '">' . word('login_forgot') . '</a>
			</div>
		</form>';
	}
	/*
	 * Return data addition form.
	 */
	function form_add($type, $field = false)
	{
		if($field) $html = '<h1>' . word('new_field') . '</h1>';
		else $html = '<h1>' . word('new_item') . '</h1>';

		$html .= '<form id="addForm" class="'.$type.'" method="post" action="'.REQURL.'" ';
		if($type == 'file') $html .= 'enctype="multipart/form-data"';
		$html .= '><input type="hidden" name="data_type" value="' . $type . '" />';

		if($field) {
			$html .= '<input type="hidden" name="field" value="true" />';
			$html .= $this->form_fields($type, '', array('default_value'=>''));
		}
		else {
			if(!$type) {
				$data = $fields = array();
			}
			else {
				$fields	= $this->get_fields($type);
				if($type == 'file') {
					if(!$fields) $fields = array();
					array_unshift($fields, 'file');
				}
				elseif(!$fields) $fields = array('value');
				$data = array_fill_keys($fields, null);
			}
			$html .= $this->form_fields($type, '', $data);
		}
		$html .= '<div class="buttons">';
		$html .= '<input type="submit" class="btn" name="action" value="' . word('add') . '" />';
		$html .= '</div></form>';
		return $html;
	}
	/*
	 * Return data edition form.
	 */
	function form_edit($type, $name)
	{
		// name / save
		$html = '<h1>';
		if($type == 'page') $html .= $this->page_title($name);
		else $html .= $name;
		$html .= '<span class="savealert">* <span class="msg">(' . word("not_saved") . ')</span></span></h1>';

		if($type == 'file') {
			$path = $this->root($this->get['path']) . '/' . $name;
			$html .= '<form method="post" action="'.REQURL.'" enctype="multipart/form-data" class="'.$type.'">';
			if(($data = $this->get($type, $path))
			|| ($data = reset($this->get($type)))) {
				$data = array_reverse($data, true);
				$data['file'] = $this->get['path'].'/'.$name;
				$data = array_reverse($data, true);;
			} else $data['file'] = $this->get['path'].'/'.$name;
		}
		else {
			$html .= '<form method="post" action="' . REQURL . '" class="'.$type.'">';
			$data = $this->get($type, $name);
		}

		$html .= $this->form_fields($type, $name, $data);

		$html .= '<input type="hidden" name="data_type" value="' . $type . '" />';
		$html .= '<div class="buttons">';
		$html .= '<input type="submit" class="btn view" name="action" value="' . word('preview') . '" id="previewBtn" />';
		$html .= '<input type="submit" class="btn edit" name="action" value="' . word('edit') . '" id="editBtn" accesskey="s"/>';
		$html .= '<input type="submit" class="btn remv" name="action" value="' . word('del') . '" id="delBtn" />';
		$html .= '</div>';

		return $html . '</form>';
	}
	/*
	 * Return html fields based on <i>item_fields</i>, used in admin forms.
	 */
	function form_fields($type, $item_name, $item_fields)
	{
		// name field
		$name_placeholder = '';
		if($type == 'file' && !isset($this->get['add_dir']) && !isset($this->get['edit_fields']))
			$name_placeholder = word('file_name');
		else $name_placeholder = $this->vacant_name($type);
		$name_field = "
			<label class='field name'>
				<div class='label'>{word('name')}</div>
				<input type='text' name='newname' class='input' value='$item_name' placeholder='$name_placeholder'/>
				<input type='hidden' name='name' value='$item_name' />
			</label>";

		// no items or item is a value.
		if($item_fields === null) return $name_field;
		if(!is_array($item_fields)) $item_fields = array('value' => $item_fields);

		// make all fields
		$custom_fields = $base_fields = '';
		foreach($item_fields as $field_name => $field_value)
		{
			// define field type
			$field_type = $field_name;
			if($field_name != 'default_value')
			{
				$model = $this->find($type, null, $field_name);
				if(is_bool($field_value) || is_bool($model) || $field_name == 'cache') {
					$field_type = 'boolean';
				}
				elseif(is_string($field_value) || is_string($model)) {
					$field_value = htmlentities($field_value, ENT_QUOTES, 'utf-8');
				}
			}

			// make html input according to field type
			$nameclass = "name='data[$field_name]' class='input'";
			switch($field_type)
			{
				case 'boolean' :
					$false = $true = ' selected="selected"';
					if($field_value) $false = '';
					else $true = '';
					$input = "
						<select $nameclass>
							<option value='false' $false>{word('no')}</option>
							<option value='true' $true>{word('yes')}</option>
						</select>";
					break;

				case 'parent' :
					$similar_items = $this->md_flatten_keys($this->find_nested($type));
					unset($similar_items[$item_name]);
					foreach($similar_items as $name => $lvl) {
						$selected = $name == $item_fields['parent'] ? 'selected="selected"' : '';
						$spaces = str_repeat('&nbsp;', $lvl * 5);
						$options .= "
							<option value='$name' $selected>
								$spaces$name
							</option>";
					}
					$selected = empty($item_fields['parent']) ? 'selected="selected"' : '';
					$input = "
						<select $nameclass>
							<option $selected></option>
							$options
						</select>";
					break;

				case 'password' :
					$ph = word('placeholder_password');
					$input = "
						<input $nameclass type='text' placeholder='$ph' />";
					break;

				case 'text' :
				case 'date' :
				case 'time' :
				case 'email' :
				case 'number' :
					$ph = word('placeholder_'.$field_type);
					$input = "
						<input $nameclass type='$field_type' value='$field_value' placeholder='$ph' />";
					break;

				case 'file':
					$is_previewable = preg_match('`(?:jpe?g|png|gif)`i', pathinfo($field_value, PATHINFO_EXTENSION));
					if($is_previewable)
						$input = "
							<div class='preview box'>
								<a href='{$this->absroot($field_value)}' title='{word('preview')}' target='_blank'>
									{$this->input_image($field_value)}
								</a>
							</div>";
					else $input = "<div>$field_value</div>";
					$input .= "<input $nameclass type='file' />";
					break;

				case 'rank' :
					$input = "<select $nameclass>";
					foreach($this->get('rank') as $rank => $rankid)
						if($rankid) {
							$input .= "<option value='$rankid' ";
							if($rankid == $field_value) $input .= 'selected="selected"';
							$input .= '>' . word($rank) . '</option>';
						}
					$input .= '</select>';
					break;

				default :
					$main = !isset($main) ? 'main' : '';
					$multiline = strpos($field_value, "\n") != -1 ? ' multiline' : '';
					$input = "
						<div class='textarea$multiline'>
							<textarea placeholder='...' $nameclass>$field_value</textarea>
							<div class='shadow input'></div>
						</div>";
			}
			// make field
			$input = "
				<label class=\"field $field_name $main\">
					<div class=\"label\">{word($field_name)}</div>
					$input
				</label>";

			// differanciate base fields and custom fields
			switch($field_name) {
				case 'date':
				case 'parent':
					$base_fields .= $input;
					break;
				default:
					$custom_fields .= $input;
			}
		}

		return "
			<div class='basefields'>
				$name_field
				$base_fields
			</div>
			<div class='customfields'>
				$custom_fields
			</div>";
	}
	/*
	 * Add to content an information message defined by a key.
	 *
	 * @param string $key The info key.
	 * @param string $info_type Optionnal. The information type (info, error).
	 * @param string $href Optionnal. Define the target of the link. Same page by default.
	 */
	function msg($key, $info_type = 'info', $additional_content = '', $href = REQURL, $all = false)
	{
		if(!$all && !$this->visitor->rank) return;

		$link = '';
		if($href) {
			if($key == 'preview')
				$link = '<a href="' . $href . '" class="undo">' . word('undo') . '</a>';
			else $link = '<a href="' . $href . '" class="close">' . word('ok') . '</a>';
		}

		$this->infos .= "
			<div class='msg $info_type'>
				<h1>{word($info_type . '_' . $key)}</h1>
				<p>{word($info_type . '_' . $key . '_msg')}</p>
				$additional_content
				$link
			</div>";
	}
	/*
	 * Add to content an information message containing a undo form.
	 *
	 * @param string $info_type The type of the action giving rise to the message.
	 * @param string $data_type The type of data manipulated by the action.
	 * @param string $data The content of the data before the action manipulation.
	 */
	function msg_undo($info_type, $data_type, $data)
	{
		$href = '?admin=' . $data_type;
		if($data_type == 'page'
		&& isset($this->get['edit'])
		&& !isset($this->get['edit_fields']))
			$href = '?page=' . $this->get['edit'];
		elseif(isset($this->get['page']))
			$href .= '&page=' . $this->get['page'];

		$this->msg($info_type, 'info',
			'<form method="post" action="'.REQURL.'" id="undoForm">'
			.'<input type="submit" name="action" value="' . word('undo') . '" class="a undo"/>'
			.'<input type="hidden" name="data_type" value="' . $data_type . '" />'
			.'<input type="hidden" name="newname" value="undo" />'
			.'<input type="hidden" name="data" value="'.base64_encode(serialize($data)).'" />'
			.'</form>');
	}
}
?>