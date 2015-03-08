<?php

/***************************************************************************
 *
 *	OUGC Similar Threads Enhancement plugin (/inc/plugins/ougc_similarthreads_enhancement.php)
 *	Author: Omar Gonzalez
 *	Copyright: © 2014 Omar Gonzalez
 *
 *	Website: http://omarg.me
 *
 *	Allows you to display the first image of a thread in the similar threads box in show threads page plus xThreads fields if installed.
 *
 ***************************************************************************
 
****************************************************************************
	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
****************************************************************************/

// Die if IN_MYBB is not defined, for security reasons.
defined('IN_MYBB') or die('Direct initialization of this file is not allowed.');

// Plugin API
function ougc_similarthreads_enhancement_info()
{
	global $ougc_similarthreads_enhancement;

	return $ougc_similarthreads_enhancement->_info();
}

// _activate() routine
function ougc_similarthreads_enhancement_activate()
{
	global $ougc_similarthreads_enhancement;

	return $ougc_similarthreads_enhancement->_activate();
}

// _is_installed() routine
function ougc_similarthreads_enhancement_is_installed()
{
	global $ougc_similarthreads_enhancement;

	return $ougc_similarthreads_enhancement->_is_installed();
}

// _uninstall() routine
function ougc_similarthreads_enhancement_uninstall()
{
	global $ougc_similarthreads_enhancement;

	return $ougc_similarthreads_enhancement->_uninstall();
}

// control_object by Zinga Burga from MyBBHacks ( mybbhacks.zingaburga.com ), 1.62
if(!function_exists('control_object'))
{
	function control_object(&$obj, $code)
	{
		static $cnt = 0;
		$newname = '_objcont_'.(++$cnt);
		$objserial = serialize($obj);
		$classname = get_class($obj);
		$checkstr = 'O:'.strlen($classname).':"'.$classname.'":';
		$checkstr_len = strlen($checkstr);
		if(substr($objserial, 0, $checkstr_len) == $checkstr)
		{
			$vars = array();
			// grab resources/object etc, stripping scope info from keys
			foreach((array)$obj as $k => $v)
			{
				if($p = strrpos($k, "\0"))
				{
					$k = substr($k, $p+1);
				}
				$vars[$k] = $v;
			}
			if(!empty($vars))
			{
				$code .= '
					function ___setvars(&$a) {
						foreach($a as $k => &$v)
							$this->$k = $v;
					}
				';
			}
			eval('class '.$newname.' extends '.$classname.' {'.$code.'}');
			$obj = unserialize('O:'.strlen($newname).':"'.$newname.'":'.substr($objserial, $checkstr_len));
			if(!empty($vars))
			{
				$obj->___setvars($vars);
			}
		}
		// else not a valid object or PHP serialize has changed
	}
}

// Dark magic
class OUGC_SimilarThreads_Enhancement
{
	function __construct()
	{
		// Run/Add Hooks
		if(!defined('IN_ADMINCP'))
		{
			global $plugins;

			$plugins->add_hook('showthread_start', array($this, 'hook_showthread_start'));
			$plugins->add_hook('forumdisplay_get_threads', array($this, 'hook_forumdisplay_get_threads'));
			$plugins->add_hook('forumdisplay_thread', array($this, 'hook_forumdisplay_thread'));
		}
	}

	// Plugin API
	function _info()
	{
		return array(
			'name'			=> 'OUGC Similar Threads Enhancement',
			'description'	=> 'Allows you to display the first image of a thread in the similar threads box in show threads page plus xThreads fields if installed.',
			'website'		=> 'http://omarg.me',
			'author'		=> 'Omar G.',
			'authorsite'	=> 'http://omarg.me',
			'version'		=> '1.0',
			'versioncode'	=> 1000,
			'compatibility'	=> '18*'
		);
	}

	// _activate() routine
	function _activate()
	{
		global $cache;

		// Insert/update version into cache
		$plugins = $cache->read('ougc_plugins');
		if(!$plugins)
		{
			$plugins = array();
		}

		$info = ougc_similarthreads_enhancement_info();

		if(!isset($plugins['similarthreads_enhancement']))
		{
			$plugins['similarthreads_enhancement'] = $info['versioncode'];
		}

		/*~*~* RUN UPDATES START *~*~*/

		/*~*~* RUN UPDATES END *~*~*/

		$plugins['similarthreads_enhancement'] = $info['versioncode'];
		$cache->update('ougc_plugins', $plugins);
	}

	// _is_installed() routine
	function _is_installed()
	{
		global $cache;

		// Insert/update version into cache
		$plugins = $cache->read('ougc_plugins');
		if(!$plugins)
		{
			$plugins = array();
		}

		return isset($plugins['similarthreads_enhancement']);
	}

	// _uninstall() routine
	function _uninstall()
	{
		global $cache;

		// Delete version from cache
		$plugins = (array)$cache->read('ougc_plugins');

		if(isset($plugins['similarthreads_enhancement']))
		{
			unset($plugins['similarthreads_enhancement']);
		}

		if(!empty($plugins))
		{
			$cache->update('ougc_plugins', $plugins);
		}
		else
		{
			$cache->delete('ougc_plugins');
		}
	}

	// Hook: showthread_start
	function hook_showthread_start()
	{
		if(function_exists('xthreads_gettfcache'))
		{
			global $threadfield_cache, $fid;
			$threadfield_cache = xthreads_gettfcache();

			$fields = '';
			foreach($threadfield_cache as $k => &$v)
			{
				$available = (!$v['forums'] || strpos(','.$v['forums'].',', ','.$fid.',') !== false);

				if($available)
				{
					$fields .= ', tfd.`'.$v['field'].'` AS `xthreads_'.$v['field'].'`';
				}
				else
				{
					unset($threadfield_cache[$k]);
				}
			}
		}

		control_object($GLOBALS['db'], '
			function query($string, $hide_errors=0, $write_query=0)
			{
				static $done = false;
				if(!$done && !$write_query && strpos($string, \'t.*, t.username AS threadusername, u.username\'))
				{
					$done = true;
					$string = strtr($string, array(
						\'t.*, t.username AS threadusername, u.username\' => \'t.*, t.username AS threadusername, u.username, p.message'.(isset($fields) ? $fields : '').'\',
						\'FROM '.TABLE_PREFIX.'threads t\' => \'FROM '.TABLE_PREFIX.'threads t
					LEFT JOIN '.TABLE_PREFIX.'posts p ON (p.pid=t.firstpost)'.(isset($fields) ? '
					LEFT JOIN '.TABLE_PREFIX.'threadfields_data tfd ON (tfd.tid=t.tid)' : '').'\'
					));
				}

				return parent::query($string, $hide_errors, $write_query);
			}
		');

		control_object($GLOBALS['templates'], '
			function get($title, $eslashes=1, $htmlcomments=1)
			{
				if($title == \'showthread_similarthreads_bit\')
				{
					$GLOBALS[\'ougc_similarthreads_enhancement\']->run_thread($GLOBALS[\'similar_thread\']);
				}

				return parent::get($title, $eslashes, $htmlcomments);
			}
		');
	}

	// Hook: forumdisplay_get_threads
	function hook_forumdisplay_get_threads()
	{

		control_object($GLOBALS['db'], '
			function query($string, $hide_errors=0, $write_query=0)
			{
				static $done = false;
				if(!$done && !$write_query && strpos($string, \'t.username AS threadusername, u.username\'))
				{
					$done = true;
					$string = strtr($string, array(
						\'t.username AS threadusername, u.username\' => \'t.username AS threadusername, u.username, p.message\',
						\'FROM '.TABLE_PREFIX.'threads t\' => \'FROM '.TABLE_PREFIX.'threads t
		LEFT JOIN '.TABLE_PREFIX.'posts p ON (p.pid=t.firstpost)\'
					));
				}

				return parent::query($string, $hide_errors, $write_query);
			}
		');
	}

	// Hook: forumdisplay_thread
	function hook_forumdisplay_thread()
	{
		global $thread;

		$this->get_post_image($thread);
	}

	// Run hack
	function run_thread(&$thread)
	{
		if(function_exists('xthreads_gettfcache'))
		{
			global $threadfield_cache, $forum_tpl_prefixes;

			isset($forum_tpl_prefixes) or $forum_tpl_prefixes = xthreads_get_tplprefixes(true);

			xthreads_set_threadforum_urlvars('thread', $thread['tid']);
			xthreads_set_threadforum_urlvars('forum', $thread['fid']);

			if(!empty($threadfield_cache))
			{
				// make threadfields array
				$thread['threadfields'] = array(); // clear previous threadfields

				foreach($threadfield_cache as $k => &$v)
				{
					if($v['forums'] && strpos(','.$v['forums'].',', ','.$thread['fid'].',') === false)
					continue;

					$tids = '0'.$GLOBALS['tids'];
					xthreads_get_xta_cache($v, $tids);

					$thread['threadfields'][$k] =& $thread['xthreads_'.$k];
					xthreads_sanitize_disp($thread['threadfields'][$k], $v, ($thread['username'] !== '' ? $thread['username'] : $thread['threadusername']));
				}
			}
			// template hack
			$tplprefix =& $forum_tpl_prefixes[$thread['fid']];

			require_once MYBB_ROOT.'inc\xthreads\xt_mischooks.php';

			xthreads_portalsearch_cache_hack($tplprefix, 'showthread_similarthreads_bit');

			if(!xthreads_empty($tplprefix))
			{
				$tplname = $tplprefix.'forumdisplay_thread_icon';
				if(!xthreads_empty($GLOBALS['templates']->cache[$tplname]))
				{
					global $lang, $mybb;
					// re-evaluate comments template
					eval('$GLOBALS[\'icon\'] = "'.$GLOBALS['templates']->get($tplname).'";');
				}
			}
		}

		$this->get_post_image($thread);
	}

	// Get first post image for thread
	function get_post_image(&$thread)
	{
		global $parser;

		$parser_options = array(
			'allow_html'		=> 1,
			'allow_mycode'		=> 1,
			'allow_smilies'		=> 0,
			'allow_imgcode'		=> 1,
			'allow_videocode'	=> 0,
			'filter_badwords'	=> 1
		);

		$thread['post'] = array('image' => '');

		preg_match_all('#<img(.+?)src=\"(.+?)\"(.+?)/>#i', (string)$parser->parse_message($thread['message'], $parser_options), $matches);

		if(!is_array($matches))
		{
			return;
		}

		$thread['post']['image'] = $matches[0][0];
	}
}

$GLOBALS['ougc_similarthreads_enhancement'] = new OUGC_SimilarThreads_Enhancement;