<?php
/*
Plugin Name: MAPP for WordPress
Plugin URI: http://github.com/lockergnome/mapp-wordpress
Description: Multi-Author Payment Plugin for WordPress
Version: 0.1-RC1
Author: Eddie Ringle
Author URI: http://eddieringle.com
License: New BSD
*/

/*
MAPP for WordPress
Copyright (c) 2011, Eddie Ringle <eddie@eringle.net>
All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials
provided with the distribution.
3. Neither the name of Lockergnome nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written
permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE
USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

require_once 'lib/FreshBooksRequest/FreshBooksRequest.php';
require_once 'admin/mapp_page.php';
require_once 'admin/mapp_admin.php';

function mapp_setup_hooks()
{
  add_action('plugins_loaded', 'mapp_page_hook_plugins');
  add_action('plugins_loaded', 'mapp_admin_hook_plugins');
}

function mapp_setup_menus()
{
  add_action('admin_menu', 'mapp_page_hook_menu');
  add_action('admin_menu', 'mapp_admin_hook_menu');
}

function mapp_setup_roles()
{
  /* Add "Accountant" role */
  add_role("mapp_accountant", "MAPP Accountant", array(
    'read' => true, // Be nice and let the accountant read posts
  ));
  /* Add "mapp_admin" capability to Admins */
  $admin_role = get_role('administrator');
  $admin_role->add_cap('mapp_admin');
}

function mapp_setup()
{
  mapp_setup_hooks();
  mapp_setup_menus();
  add_action('admin_init', 'mapp_setup_roles');
}

if (is_admin()) {
  mapp_setup();
}
?>
