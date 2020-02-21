<?php
/*
Plugin Name: Breadcrumb NavXT BuddyPress Extensions
Plugin URI: https://mtekk.us/extensions/breadcrumb-navxt-buddypress-extensions
Description: Fixes a few edge cases that BuddyPress presents. For details on how to use this plugin visit <a href="https://mtekk.us/extensions/breadcrumb-navxt-buddypress-extensions">Breadcrumb NavXT BuddyPress Extensions</a>. 
Version: 1.0.3
Author: John Havlik
Author URI: http://mtekk.us/
License: GPL2
TextDomain: breadcrumb-navxt-buddypress
DomainPath: /languages/
*/
/*  Copyright 2014-2020  John Havlik  (email : john.havlik@mtekk.us)

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
		bcn_bp_do_directory($breadcrumb_trail, 'members');
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
		bcn_bp_do_directory($breadcrumb_trail, 'groups');
		if($breadcrumb_trail->opt['bcurrent_item_linked'] || !(bp_is_group_home()|| bp_is_group_create()))
		{
			$breadcrumb_trail->breadcrumbs[0]->set_url(bp_get_group_permalink(groups_get_current_group()));
		}
		if(!bp_is_group_home() && !bp_is_group_create())
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
	if(method_exists($breadcrumb_trail->breadcrumbs[0], 'remove_types'))
	{
		$breadcrumb_trail->breadcrumbs[0]->remove_types(array('current-item'));
	}
}
/**
 * A Breadcrumb Trail injection Function
 * 
 * This recursive functions fills the trail with breadcrumbs for parent posts/pages.
 * @param int $id The id of the parent page.
 * @param int $frontpage The id of the front page.
 * @return WP_Post The parent we stopped at
 */
function bcn_bp_post_parents(&$breadcrumb_trail, $id, $frontpage, $depth)
{
	//Use WordPress API, though a bit heavier than the old method, this will ensure compatibility with other plug-ins
	$parent = get_post($id);
	//Place the breadcrumb in the trail, uses the constructor to set the title, template, and type, get a pointer to it in return
	$breadcrumb = new bcn_breadcrumb(
			get_the_title($id),
			$breadcrumb_trail->opt['Hpost_' . $parent->post_type . '_template'],
			array('post', 'post-' . $parent->post_type),
			get_permalink($id),
			$id,
			true);
	array_splice($breadcrumb_trail->breadcrumbs, $depth, 0, array($breadcrumb));
	//Make sure the id is valid, and that we won't end up spinning in a loop
	if($parent->post_parent >= 0 && $parent->post_parent != false && $id != $parent->post_parent && $frontpage != $parent->post_parent)
	{
		//If valid, recursively call this function
		bcn_bp_post_parents($breadcrumb_trail, $parent->post_parent, $frontpage, ++$depth);
	}
}
/**
 * A quasi generic directory page breadcrumb injection function
 * 
 * @param bcn_breadcrumb_trail $breadcrumb_trail The breadcrumb trail object to modify
 * @param string $resource The name of the BuddyPress resource to generate the directory trail for 
 */
function bcn_bp_do_directory(&$breadcrumb_trail, $resource)
{
	$directory_pages = bp_core_get_directory_page_ids();
	bcn_bp_post_parents($breadcrumb_trail, $directory_pages[$resource], get_option('page_on_front'), 1);
}
function bcn_bp_do_group_create(&$breadcrumb_trail)
{
	$breadcrumb = new bcn_breadcrumb(
			__('Create a Group', 'breadcrumb-navxt-buddypress'),
			null,
			array('groups', 'create-new-group', 'current-item'),
			bp_get_root_domain() . '/' . bp_get_groups_root_slug() . '/create');
	if($breadcrumb_trail->opt['bcurrent_item_linked'])
	{
		$breadcrumb->set_linked(true);
	}
	array_unshift($breadcrumb_trail->breadcrumbs, $breadcrumb);
}
function bcn_bp_do_user(&$breadcrumb_trail)
{
	$bp = buddypress();
	if(!isset($bp->members->nav))
	{
		return;
	}
	//Loop around the nav items until we find the one we are on
	foreach((array) $bp->members->nav->get_item_nav() as $user_nav_item)
	{
		if(bp_is_current_component($user_nav_item['slug']))
		{
			if(bp_loggedin_user_domain())
			{
				$url = str_replace(bp_loggedin_user_domain(), bp_displayed_user_domain(), $user_nav_item['link']);
			}
			else
			{
				$url = trailingslashit(bp_displayed_user_domain() . $user_nav_item['link']);
			}
			//Now add the breadcrumb
			$breadcrumb = new bcn_breadcrumb(
					bcn_bp_clean_name($user_nav_item['name']),
					null,
					array('member', 'member-' . $user_nav_item['slug'], 'current-item'),
					$url);
			if($breadcrumb_trail->opt['bcurrent_item_linked'])
			{
				$breadcrumb->set_linked(true);
			}
			array_unshift($breadcrumb_trail->breadcrumbs, $breadcrumb);
			return;
		}
	}
}
function bcn_bp_do_group(&$breadcrumb_trail)
{
	$bp = buddypress();
	if(!isset($bp->groups->nav))
	{
		return;
	}
	//Loop around the nav items until we find the one we are on
	foreach((array) $bp->groups->nav->get_secondary(array('parent_slug' => bp_current_item(), 'user_has_access' => true)) as $nav_item)
	{
		if(bp_current_action() === $nav_item['slug'])
		{
			//Now add the breadcrumb
			$breadcrumb = new bcn_breadcrumb(
					bcn_bp_clean_name($nav_item['name']),
					null,
					array('group', 'group-' . $nav_item['slug'], 'current-item'),
					$nav_item['link']);
			if($breadcrumb_trail->opt['bcurrent_item_linked'])
			{
				$breadcrumb->set_linked($true);
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
