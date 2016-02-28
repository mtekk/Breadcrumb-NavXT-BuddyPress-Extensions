<?php
/*
Plugin Name: Breadcrumb NavXT BuddyPress Extensions
Plugin URI: https://mtekk.us/extensions/breadcrumb-navxt-buddypress-extensions
Description: Fixes a few edge cases that BuddyPress presents. For details on how to use this plugin visit <a href="https://mtekk.us/extensions/breadcrumb-navxt-buddypress-extensions">Breadcrumb NavXT BuddyPress Extensions</a>. 
Version: 0.1.0
Author: John Havlik
Author URI: http://mtekk.us/
License: GPL2
TextDomain: breadcrumb-navxt-buddypress
DomainPath: /languages/
*/
/*  Copyright 2014-2016  John Havlik  (email : john.havlik@mtekk.us)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
require_once(dirname(__FILE__) . '/includes/block_direct_access.php');
add_action('plugins_loaded', 'bcn_bp_load_plugin_textdomain');
function bcn_bp_load_plugin_textdomain()
{
	load_plugin_textdomain('breadcrumb-navxt-buddypress', FALSE, basename(dirname(__FILE__)) . '/languages/');
}
add_action('bcn_after_fill', 'bcn_bp_filler', 11);
/**
 * Hooks into the bcn_after_fill action and deals with filling the BuddyPress items
 * 
 * @param bcn_breadcrumb_trail &$breadcrumb_trail The Breadcrumb NavXT breadcrumb trail object that we're playing with
 */
function bcn_bp_filler($breadcrumb_trail)
{
	//Exit early is this is not a BuddyPress resource
	if(!function_exists('is_buddypress') || !is_buddypress())
	{
		return;
	}
	//Handle user pages
	if(bp_is_user())
	{
		//Start by adding in the directory link (so that we have a known good position)
		bcn_bp_do_members_directory($breadcrumb_trail);
		if($breadcrumb_trail->opt['bcurrent_item_linked'] || !bp_is_user_activity())
		{
			$breadcrumb_trail->breadcrumbs[0]->set_url(bp_displayed_user_domain());
		}
		if(!bp_is_user_activity())
		{
			bcn_bp_remove_current_item($breadcrumb_trail);
			bcn_bp_do_user($breadcrumb_trail);
		}
	}
	//Handle group pages
	else if(bp_is_group())
	{
		bcn_bp_do_groups_directory($breadcrumb_trail);
		if($breadcrumb_trail->opt['bcurrent_item_linked'])
		{
			$breadcrumb_trail->breadcrumbs[0]->set_url(bp_get_group_permalink(groups_get_current_group()));
		}
		if(!bp_is_group_home())
		{
			bcn_bp_do_group($breadcrumb_trail);
		}
	}
	//Need to add a type for members directory pages, possibly a link too
	else if(bp_is_members_directory())
	{
		$breadcrumb_trail->breadcrumbs[0]->add_type('members-directory');
		if($breadcrumb_trail->opt['bcurrent_item_linked'])
		{
			$breadcrumb_trail->breadcrumbs[0]->set_url(bp_get_members_directory_permalink());
		}
	}
	//Need to add a type for groups directory pages, possibly a link too
	else if(bp_is_groups_directory() || bp_is_group_create())
	{
		$breadcrumb_trail->breadcrumbs[0]->add_type('groups-directory');
		if($breadcrumb_trail->opt['bcurrent_item_linked'] || bp_is_group_create())
		{
			$breadcrumb_trail->breadcrumbs[0]->set_url(bp_get_groups_directory_permalink());
		}
		if(bp_is_group_create())
		{
			bcn_bp_remove_current_item($breadcrumb_trail);
			bcn_bp_do_group_create($breadcrumb_trail);
		}
	}
}
/**
 * Removes the current-item type from the current item (key 0 in the breadcrumbs array)
 * 
 * @param bcn_breadcrumb_trail $breadcrumb_trail The breadcrumb trail object to modify
 */
function bcn_bp_remove_current_item(&$breadcrumb_trail)
{
	if($key = array_search('current-item', $breadcrumb_trail->breadcrumbs[0]->type))
	{
		unset($breadcrumb_trail->breadcrumbs[0]->type[$key]);
	}
}
function bcn_bp_do_members_directory(&$breadcrumb_trail)
{
	$breadcrumb = new bcn_breadcrumb(_x('Members', 'Page title for the Members directory.', 'breadcrumb-navxt-buddypress'), null, array('members', 'members-directory'), bp_get_members_directory_permalink());
	array_splice($breadcrumb_trail->breadcrumbs, 1, 0, array($breadcrumb));
}
function bcn_bp_do_groups_directory(&$breadcrumb_trail)
{
	$breadcrumb = new bcn_breadcrumb(_x('Groups', 'Page title for the Groups directory.', 'breadcrumb-navxt-buddypress'), null, array('groups', 'groups-directory'), bp_get_groups_directory_permalink());
	array_splice($breadcrumb_trail->breadcrumbs, 1, 0, array($breadcrumb));
}
function bcn_bp_do_group_create(&$breadcrumb_trail)
{
	$breadcrumb = new bcn_breadcrumb(__('Create a Group', 'breadcrumb-navxt-buddypress'), null, array('groups', 'create-new-group', 'current-item'));
	if($breadcrumb_trail->opt['bcurrent_item_linked'])
	{
		$breadcrumb->set_url(bp_get_root_domain() . '/' . bp_get_groups_root_slug() . '/create');
	}
	array_unshift($breadcrumb_trail->breadcrumbs, $breadcrumb);
}
function bcn_bp_do_user(&$breadcrumb_trail)
{
	$bp = buddypress();
	//Loop around the nav items until we find the one we are on
	foreach((array) $bp->bp_nav as $user_nav_item)
	{
		if(bp_is_current_component($user_nav_item['slug']))
		{
			//Now add the breadcrumb
			$breadcrumb = new bcn_breadcrumb(bcn_bp_clean_name($user_nav_item['name']), null, array('member', 'member-' . $user_nav_item['slug'], 'current-item'));
			if($breadcrumb_trail->opt['bcurrent_item_linked'])
			{
				if(bp_loggedin_user_domain())
				{
					$url = str_replace(bp_loggedin_user_domain(), bp_displayed_user_domain(), $user_nav_item['link']);
				}
				else
				{
					$url = trailingslashit(bp_displayed_user_domain() . $user_nav_item['link']);
				}
				$breadcrumb->set_url($url);
			}
			array_unshift($breadcrumb_trail->breadcrumbs, $breadcrumb);
			return;
		}
	}
}
function bcn_bp_do_group(&$breadcrumb_trail)
{
	$bp = buddypress();
	$index = bp_current_item();
	//Loop around the nav items until we find the one we are on
	foreach((array) $bp->bp_options_nav[$index] as $nav_item)
	{
		if(bp_current_action() === $nav_item['slug'])
		{
			//Now add the breadcrumb
			$breadcrumb = new bcn_breadcrumb(bcn_bp_clean_name($nav_item['name']), null, array('group', 'group-' . $nav_item['slug'], 'current-item'));
			if($breadcrumb_trail->opt['bcurrent_item_linked'])
			{
				$breadcrumb->set_url($nav_item['link']);
			}
			array_unshift($breadcrumb_trail->breadcrumbs, $breadcrumb);
			return;
		}
	}
}
/**
 * Removes the annoying number and span from nav item names in BuddyPress
 * 
 * @param string $string The name to remove the span/digit from
 * 
 * @return string The name sans span and digit(s)
 */
function bcn_bp_clean_name($string)
{
	return trim(preg_replace('/\<span.*\>\d*\<\/span\>/', '', $string));
}
