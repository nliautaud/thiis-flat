<?php
require_once '../index.php';
require_once 'admin.php';
include 'lang.php';
?>
<!doctype html>
<!-- THIIS is another cms. -->
<html>
	<head>
		<meta charset="utf-8">
		<meta name="robots" content="noindex">
		<meta name="googlebot" content="noindex">
		<meta name="thiis" data_type="<?php echo $type; ?>">
		<title><?php echo $thiis->setting('site_title') . ' - ' . word('admin'); ?></title>
		<?php
			if($site_position) echo $thiis->css($output, 'siteframe');
		?>
	<body>
		<aside id="adminframe">
			<header>
				<div class="close">
					<?php echo $thiis->parse_link(PAGENAME, word('close')) ?>
				</div>
				<div class="version">
					<a href="<?php echo THIISURL ?>">thiis</a>
					v<?php echo date('y.m.d', $thiis->version) ?>
				</div>
				<?php
				// logout link
				if(isset($thiis->visitor->name)): ?>
				<div class="logout">
					<a href="?logout"><?php echo word('logout') ?></a>
					(<?php echo $thiis->visitor->name ?>)
				</div>
				<?php endif; ?>
			</header>
			<?php
			// LOGIN
			if(!isset($thiis->visitor->name)):
				if($firstlogin = !$thiis->get('user'))
					echo $thiis->msg('firstlogin', 'info');
				echo $thiis->form_login(false);
			?>

			<?php
			// CONTENT
			else:
			?>
			<nav role="navigation">
				<?php echo $thiis->menu() ?>
			</nav>
			<aside class="messages">
				<?php echo $thiis->infos ?>
			</aside>
			<main class="content">
				<?php
				$type = $thiis->current();
				switch($type) {
					case false :	break;
					case 'data' :	echo $thiis->management(); break;
					case 'file' :	echo $thiis->management_file(); break;
					default : 		echo $thiis->management_data($type); break;
				}
				?>
			</main>
			<?php
			endif; // END CONTENT
			?>
		</aside>

		<?php
		// CodeMirror
		if($cm = $thiis->setting("codemirror_path")):
		?>
		<link rel="stylesheet" href="<?php echo $cm ?>/lib/codemirror.css">
		<script src="<?php echo $cm ?>/lib/codemirror.js"></script>
		<script src="<?php echo $cm ?>/mode/php/php.js"></script>
		<script src="<?php echo $cm ?>/mode/css/css.js"></script>
		<script src="<?php echo $cm ?>/mode/xml/xml.js"></script>
		<script src="<?php echo $cm ?>/mode/javascript/javascript.js"></script>
		<script src="<?php echo $cm ?>/mode/clike/clike.js"></script>
		<script>
			form = document.querySelector('#adminframe main form');
			var areas = form.querySelectorAll('.textarea textarea');
			var type = form.querySelector('main form input[name=data_type]').value;
			for(var i in areas)
				CodeMirror.fromTextArea(areas[i], {
					lineNumbers: true,
					matchBrackets: true,
					mode: type == 'style' ? 'text/css' : 'application/x-httpd-php',
					indentUnit: 4,
					indentWithTabs: true,
					lineWrapping: true,
					tabMode: "shift",
					viewportMargin: 20,
					extraKeys: {
						"Ctrl-S": function() { form.querySelector('.btn.edit').click(); },
						"Ctrl-P": function() { form.querySelector('.btn.view').click(); }
					  }
				});
		</script>
		<?php
		// Default scripts
		else:
		?>
		<script type="text/javascript">
			// cross-browser event
			function efix(e) {
				e = e || window.event;
				e.target = e.target || e.srcElement || e.originalTarget;
				e.keyCode = e.keyCode || e.wich;
				return e;
			}
			// cross-browser eventListeners
			function listen(el, type, listener, useCapture) {
				if(el.addEventListener){
					el.addEventListener(type, listener, useCapture);
				} else if (el.attachEvent) {
					el.attachEvent('on' + type, listener);
				}
			}
			function listenAll(els, type, listener, useCapture) {
				for(var i=0, el; el=els[i]; i++) {
					listen(el, type, listener, useCapture);
				}
			}
			function addClass(el, cl){
				el.className += ' '+cl;
			}
			function removeClass(el, cl){
				var r = new RegExp('((^| +)'+cl+')( |$)', 'g'),
					c = el.className.match(r);
				el.className = el.className.replace(r, '$3');
				return c;
			}
			function toggleClass(el, cl){
				if(!removeClass(el, cl)) addClass(el, cl);
			}

			function ajaxRequest() {
				if(window.ActiveXObject){
					for(var mode in ["Msxml2.XMLHTTP", "Microsoft.XMLHTTP"]){
						try{ return new ActiveXObject(mode) }
						catch(e){}
					}
				}
				if (window.XMLHttpRequest) return new XMLHttpRequest()
				return false
			}

			// selection tools
			function getSelection(e) {
				return {
					start: e.selectionStart,
					end: e.selectionEnd,
					value: e.value.slice(e.selectionStart, e.selectionEnd)
				};
			}
			function setSelection(e, start, end) {
				e.focus();
				e.selectionStart = start;
				e.selectionEnd = end;
				return getSelection(e);
			}
			function replaceSelection(e, string) {
				var selection = getSelection(e),
					txtEvt = document.createEvent('TextEvent');

				txtEvt.initTextEvent('textInput', true, true, null, string);
				e.dispatchEvent(txtEvt);

				return setSelection(e, selection.start, selection.start + string.length);
			}
			function extendSelection(e, extendTo, ifRangeSelectToo) {
				var selection = getSelection(e);

				if(extendTo === undefined
				|| (ifRangeSelectToo === undefined && selection.value.length))
					return selection;

				var before = e.value.substr(0, selection.start),
					after = e.value.substr(selection.end),
					linestart = 0;

				if(extendTo == ' ') var linestart = before.lastIndexOf('\n') + 1;

				var lextend = before.substr(linestart).lastIndexOf(extendTo) + linestart;
				if (lextend == -1) lextend = 0;

				if(extendTo == ' ') var rextend = after.search(/(\n| )/);
				else var rextend = after.indexOf(extendTo);
				if (rextend == -1) rextend = e.value.length - selection.end;
				return setSelection(e, lextend + extendTo.length, selection.end + rextend);
			}
			function wrapSelection(e, left, right) {
				var selection = getSelection(e),
					txtEvt = document.createEvent('TextEvent');

				txtEvt.initTextEvent('textInput', true, true, null, left + selection.value + right);
				e.dispatchEvent(txtEvt);

				return setSelection(e, selection.start + left.length, selection.end + left.length);
			}
		</script>
		<?php
		// EDITION script
		if(!empty($thiis->get['edit']) || isset($thiis->get['add'])):
		?>
		<script type="text/javascript">

			main = document.querySelector('#adminframe main');
			title = main.querySelector('h1');
			form = main.querySelector('form');

			// not-saved alert
			var fields = form.querySelectorAll('.input');
			listenAll(fields, 'input', function() {
				title.className = 'notsaved';
			});

			// close messages
			var close = document.querySelectorAll(".close");
			listenAll(close, 'click', function(e) {
				e = efix(e);
				e.preventDefault();
				addClass(e.target.parentNode, 'hide');
				return false;
			});

			// general shortcuts
			listen(document, 'keydown', function(e) {
				e = efix(e);
				var tgtType = e.target.tagName.toLowerCase();

				// close messages with echap
				var close = document.querySelector('.messages .close');
				if(e.keyCode == 27 && close) {
					close.click();
					return false;
				}
				else if(e.ctrlKey) {
					// undo with ctrl + z
					var undo = document.querySelector('.messages .undo');
					if(e.keyCode == 90 && undo && tgtType != "input" && tgtType != "textarea") {
						undo.click();
						return false;
					}
					// save with ctrl + s
					var edit = form.querySelector('.btn.edit');
					if(e.keyCode == 83 && edit) {
						edit.click();
						return false;
					}
					// preview with ctrl + p
					var view = form.querySelector('.btn.view');
					if(e.keyCode == 80 && view) {
						view.click();
						return false;
					}
				}
			});


			// Toolbar

			var imageDragStart = function(event, path) {
				var icon = document.createElement("img");
				icon.src = "?image="+path+"&h=200";
				event.dataTransfer.setDragImage(icon, -10, -10);
				event.dataTransfer.setData("Text", "{{"+path+"}}");
			}

			<?php
			$page_insert = '';
			$pages = $thiis->find('page');
			foreach($pages as $page)
				$page_insert .= "<option value=\"$page\">$page - {$thiis->page_title($page)}</option>";
			?>
			var toolbar = document.createElement('div');
			toolbar.className = 'toolbar';
			toolbar.innerHTML = ''+
				'<button data-tag="**"><b>b</b></button>'+
				'<button data-tag="//"><em>i</em></button>'+
				'<button data-tag="__"><u>u</u></button>'+
				'<button data-tag="--"><del>d</del></button>'+
				'<select id="insert_page" class="btn">'+
					'<option hidden>Link to page</option>'+
					<?php echo "'$page_insert'+"; ?>
				'</select>'+
				'<input id="insert_url" class="btn" type="text" value="http://" />'+
				'<span class="select">'+
					'<button class="switcher">≡ Lists</button>'+
					'<span class="options">'+
						'<button data-list="*">&bull; Unordered</button>'+
						'<button data-list="-">1. Ordered</button>'+
					'</span>'+
				'</span>'+
				'<span class="select">'+
					'<button class="switcher"># Titles</button>'+
					'<span class="options">'+
						'<button data-tag="\n#########\n">Main title</button>'+
						'<button data-tag="\n=========\n">Subtitle</button>'+
					'</span>'+
				'</span>'+
				'<button class="images-switcher">☐ Images</button>'+
				'<div class="images-list"></div>';

			main.insertBefore(toolbar, form);

			// Toolbar switchs events

			switchers = toolbar.querySelectorAll('.switcher');
			listen(document, 'click', function(e) {
				var e = efix(e);
				for(var i=0, s; s=switchers[i]; i++){
					if(s != e.target) removeClass(s, 'show');
					else addClass(s, 'show');
				}
			});

			// Areas

			areas = form.querySelectorAll('.field textarea');

			function populateShadow(area) {
				var parent = area.parentNode;
				var shadow = area.nextElementSibling;
				var val = area.value.replace(/</g, '&lt;');
				shadow.innerHTML = val;
				if(val.indexOf("\n") != -1) {
					shadow.innerHTML += '\n\n';
					parent.className = 'textarea multiline';
				}
				else if(removeClass(parent, 'multiline'))
					shadow.style.height = 'auto';
			}

			// populate shadow on start and edit
			for(var i=0, area; area=areas[i]; i++) {
				populateShadow(area);
				var populate = function(e) { populateShadow(efix(e).target); }
				listen(area, 'keyup', populate);
				listen(area, 'keydown', populate);
			}

			// store last selected (or first)
			currArea = areas[0];
			listenAll(areas, 'focus', function(e) {
				currArea = efix(e).target;
			});

			// resize main area to fit max space
			if(currArea.value.indexOf("\n") != -1) {
				var emptySpace =  adminframe.offsetHeight - (form.offsetTop + form.offsetHeight),
					shadow = currArea.nextElementSibling;
				shadow.style.height = shadow.offsetHeight + emptySpace + 'px';
				shadow.style.maxHeight = currArea.style.maxHeight = shadow.style.height;
			}

			// tabulations
			listenAll(areas, 'keydown', function(e)
			{
				e = efix(e);
				if(e.keyCode != 9) return;
				e.preventDefault();

				var sel = getSelection(currArea);

				// insert unique
				if(sel.value == '' && !e.shiftKey)
					return wrapSelection(currArea, '\t', '');

				// extend selection to full lines
				var extendedSel = extendSelection(currArea, '\n', true),
					extendedSel = setSelection(currArea, extendedSel.start-1, extendedSel.end);

				// add or remove tabs at every newline and count replacements
				var search = new RegExp('\n' + (e.shiftKey ? '\t' : ''), 'g'),
					replace = '\n' + (e.shiftKey ? '' : '\t'),
					nbrInside = (sel.value.match(search)||[]).length,
					nbrBefore = (extendedSel.value.match(search)||[]).length - nbrInside;
				replaceSelection(currArea, extendedSel.value.replace(search, replace))

				// update original selection
				if(!e.shiftKey)
					setSelection(currArea, sel.start + nbrBefore, sel.end + nbrBefore + nbrInside);
				else
					setSelection(currArea, sel.start - nbrBefore, sel.end - nbrBefore - nbrInside);
			});

			// TOOLBAR insertions

			// basic
			var insertBtns = toolbar.querySelectorAll('button[data-tag]');
			listenAll(insertBtns, 'click', function(e) {
				var e = efix(e),
					tag = e.target.getAttribute('data-tag') || e.target.parentNode.getAttribute('data-tag');
				tag = tag.replace(/\\n/g, "\n");

				extendTo = tag.indexOf('\n') != -1 ? '\n' : ' ';

				extendSelection(currArea, extendTo);
				wrapSelection(currArea, tag, tag);
				populateShadow(currArea);
			});

			// links
			var insert_link = function(e) {
				var e = efix(e);

				// only entry key for text input
				if(e.target.id == "insert_url" && e.keyCode != 13) return;
				e.preventDefault();

				var content = e.target.value;
				if(!content) return;

				// reset input
				if(e.target.nodeName.toLowerCase() == 'select') e.target.selectedIndex = 0;
				else e.target.value = 'http://';

				extendSelection(currArea, ' ');
				var selection = getSelection(currArea);
				if(selection.value.length)
					content += '>';

				wrapSelection(currArea, '[[' + content, ']]');
				populateShadow(currArea);
			}
			var urlInput = document.getElementById('insert_url');
			listen(urlInput, 'click', function(e) {
				e = efix(e);
				setSelection(e.target, e.target.value.length, e.target.value.length);
			});
			urlInput.onkeypress = insert_link;
			document.getElementById('insert_page').onchange = insert_link;

			// lists
			var listBtns = toolbar.querySelectorAll('button[data-list]');
			listenAll(listBtns, 'click', function(e) {
				e = efix(e);
				var type = e.target.getAttribute('data-list') || e.target.parentNode.getAttribute('data-list'),
					sel = extendSelection(currArea, '\n', true),
					sel = setSelection(currArea, sel.start-1, sel.end),
					val = sel.value.replace(/\n[ \t]?([ \t]*)/g, '\n$1\t'+type+' '),
					sel = replaceSelection(currArea, val);
				setSelection(currArea, sel.start+1, sel.end);
			});

			<?php
			// images list for toolbar quick insert
			$image_insert = '';
			$images = $thiis->file_get($thiis->path(), 'image');
			foreach($images as $path => $image) {
				$image_insert .= "<div class='image' style='background-image:url(?image=$path&h=200)' ";
				$image_insert .= "ondragstart='imageDragStart(event, \\\"$path\\\"); this.style.opacity=.3;' ";
				$image_insert .= "ondragend='this.style.opacity=1' ";
				$image_insert .= "draggable='true'><img src='?image=$path&h=200'/></div>";
			}
			?>
			// images load and switch
			imgSwitch = toolbar.querySelector('.images-switcher');
			listen(imgSwitch, 'click', function() {
				var tgt = toolbar.querySelector('.images-list');
				if(tgt.innerHTML == "")
					tgt.innerHTML = <?php echo '"'.$image_insert.'"' ?>;
				toggleClass(imgSwitch, 'show');
			});

		</script>
		<?php
		// LISTING script
		else:
		?>
		<script type="text/javascript">

			meta = document.querySelector('meta[name=thiis]');
			main = document.querySelector('#adminframe main');
			data_type = meta.getAttribute('data_type');


			// ITEMS LIST columns filtering

			if((btn = main.querySelector("#columnsFilter>.btn"))) {
				btn.style.display = 'none';
				var boxes = main.querySelectorAll("#columnsFilter>label>input");
				listenAll(boxes, 'click', function(e) {
					e = efix(e);
					e.target.form.submit();
				});
			}

			// DRAG & DROP

			itemName = function(i) {
				return i.querySelector('.column.name').innerHTML;
			}
			updateMsgAndView = function(r)
			{
				r.onreadystatechange = function() {
					if(r.readyState == 4) {
						console.log(r.responseText);
						// make element from response for DOM traversing
						var responseHTML = document.createElement('div');
						responseHTML.innerHTML = r.responseText;

						// get some parts
						var new_msgs = responseHTML.querySelector('#adminframe .messages');
						var new_site = responseHTML.querySelector('#siteframe');

						// replace
						document.querySelector('#adminframe .messages').innerHTML = new_msgs.innerHTML;
						document.querySelector('#siteframe').innerHTML = new_site.innerHTML;
					}
				}
			}

			list = main.querySelectorAll('.list');
			items = main.querySelectorAll('.list .item');
			items_core = main.querySelectorAll('.list .item span');

			delzone = document.createElement('div');
			delzone.className = 'delzone';
			main.appendChild(delzone);
			listen(delzone, 'dragenter', function(e) {
				if(dragged == undefined) return;
				addClass(this, 'over');
			});
			listen(delzone, 'dragover', function(e) {
				e = efix(e);
				e.preventDefault();
				return false;
			});
			listen(delzone, 'dragleave', function(e) {
				if(dragged == undefined) return;
				removeClass(this, 'over');
			});
			listen(delzone, 'drop', function(e)
			{
				if(dragged == undefined) return;
				e = efix(e);
				e.preventDefault();

				removeClass(this, 'over');

				// list all childs items names
				var names = '';
				[].forEach.call(dragged.querySelectorAll('.column.name'), function(n) {
					names += n.innerHTML + ' & ';
				});
				names = names.slice(0, -3);

				// confirm
				var d = dragged;
				var confirmMsg = confirm(<?php echo '\''.word('del').' \''; ?> + names + ' ?');
				if(!confirmMsg) return;

				// delete
				d.parentNode.removeChild(d);

				var r = new ajaxRequest();
				updateMsgAndView(r);
				var parameters = 'action=del'
					+ '&newname=' + itemName(d)
					+ '&data_type=' + encodeURIComponent(data_type);

				r.open('POST', '', true);
				r.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
				r.send(parameters);
			});


			listenAll(items, 'dragstart', function(e) {
				dragged = this;
				addClass(this, 'dragged');
				addClass(delzone, 'show');

				e = efix(e);
				e.stopPropagation();
				e.dataTransfer.effectAllowed = 'move';
			});
			listenAll(items, 'dragover', function(e) {
				e = efix(e);
				e.preventDefault();
				e.stopPropagation();
				e.dataTransfer.dropEffect = 'move';
				return false;
			});
			listenAll(items, 'dragenter', function(e) {
				if(dragged == undefined || this == dragged)
					return;
				e = efix(e);
				e.stopPropagation();
				addClass(this, 'over');
			});
			listenAll(items, 'dragleave', function(e) {
				e = efix(e);
				e.stopPropagation();
				removeClass(this, 'over');
			});
			endDrag = function(e) {
				removeClass(dragged, 'dragged');
				removeClass(delzone, 'show');
				[].forEach.call(items, function (item) {
					removeClass(item, 'over');
					removeClass(item, 'overin');
				});
				dragged = undefined;
			}
			arrangeItem = function(o)
			{
				var	parameters = 'action=arrange'
						+'&data_type=' + encodeURIComponent(data_type)
						+'&source=' + itemName(dragged)
						+'&target='+ itemName(o.after)
						+'&parent=' + (o.parent !== null ? itemName(o.parent) : '');

				var r = new ajaxRequest();
				updateMsgAndView(r);
				r.open('POST', '', true);
				r.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
				r.send(parameters);
			}
			listenAll(items, 'drop', function(e) {
				if(dragged == undefined) return;
				e = efix(e);
				e.stopPropagation();

				if(this != dragged)
				{
					var parentItem = this.parentNode.parentNode;
					if(parentItem.tagName.toLowerCase() != 'li') parentItem = null;
					arrangeItem({after:this, parent:parentItem});

					if(this.nextSibling)
						this.parentNode.insertBefore(dragged, this.nextSibling);
					else this.parentNode.appendChild(dragged);
				}
				endDrag();
				return false;
			});
			listenAll(items, 'dragend', endDrag);


			listenAll(items_core, 'dragenter', function(e) {
				var item = this.parentNode.parentNode;
				if(dragged == undefined || item == dragged)
					return;

				e = efix(e);
				e.stopPropagation();
				addClass(item, 'overin');
			});
			listenAll(items_core, 'dragleave', function(e) {
				e = efix(e);
				e.stopPropagation();
				removeClass(this.parentNode.parentNode, 'overin');
			});
			listenAll(items_core, 'drop', function(e) {
				if(dragged == undefined) return;
				e = efix(e);
				e.stopPropagation();

				var target = this.parentNode.parentNode;
				if(target != dragged){
					var childsList = target.querySelector('.list');
					if(childsList) {
						var lastChild = target.querySelector('.list>li:last-child');
						arrangeItem({after:lastChild, parent:target});
					} else {
						childsList = document.createElement('ul');
						childsList.className = 'list';
						target.appendChild(childsList);
						arrangeItem({after:target, parent:target});
					}
					childsList.appendChild(dragged);
				}
				endDrag();
				return false;
			});
			listenAll(items_core, 'dragend', endDrag);


		</script>
		<?php
		endif;
		endif;
		// END ADMIN SCRIPTS

		// SITEFRAME
		if($site_position):
		?>
		<section id="siteframe">
			<div id="html">
				<?php
				// Extract site body
				$site_body_begin = strpos($output, '<body');
				$site_body_end = strrpos($output, '</body>');
				$site_body = substr($output, $site_body_begin + 5, $site_body_end);
				$site_body = '<div id="body"' . $site_body . '</div>';

				// remove scripts
				$site_body = preg_replace('`<script[^>]*>.*?</script>`i', '', $site_body);

				// Replace pages links by administration links with page previewed
				if($thiis->setting('use_fancy_url')) {
					$page_pattern = '`(<a href="[^"]+/[^"#/]*)((?:#[^"]+)?")`';
					$del = '?';
				} else {
					$page_pattern = '`(<a href="[^\?]+\?page=[^"#]*)((?:#[^"]+)?")`';
					$del = '&';
				}
				echo preg_replace($page_pattern, '\\1'.$del.'admin='.$type.'&edit='.@$thiis->get['edit'].'\\2', $site_body);
				?>
			</div>
		</section>
		<?php
		endif;
		// END SITEFRAME
		?>
	</body>
</html>