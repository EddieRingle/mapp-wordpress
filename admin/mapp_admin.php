<?php
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

global $admin_options;
$admin_options = array(
  array(
    'slug' => 'mapp_post_flat_rate',
    'title' => 'Post flat rate',
    'description' => 'Flat rate per post to pay writers',
    'type' => 'money',
    'default' => 40.00),
  array(
    'slug' => 'mapp_post_word_rate',
    'title' => 'Post Per-word rate',
    'description' => 'Per-word rate to pay writers',
    'type' => 'money',
    'default' => 0.10)
);

function mapp_admin_render()
{
  global $admin_options;
  if (isset($_POST) && isset($_POST['submit'])) {
    check_admin_referer('mapp_admin_save', 'mapp_nonce');
    foreach($admin_options as $opt) {
      if (isset($_POST[$opt['slug']])) {
        update_option($opt['slug'], $_POST[$opt['slug']]);
      } else {
        update_option($opt['slug'], $opt['default']);
      }
    }
  }
  ?>
  <div class="wrap">
    <h2>MAPP Admin</h2>
    <form name="mapp_admin_form" method="post">
      <input type="hidden" name="page" value="mapp_admin" />
      <?php wp_nonce_field('mapp_admin_save', 'mapp_nonce'); ?>
      <table class="form-table">
        <?php
        foreach($admin_options as $opt) {
        ?>
        <tr valign="top">
          <th scope="row"><?php echo $opt['title']; ?></th>
          <td>
            <?php
            if ($opt['type'] == 'money') {
            $value = number_format(round(get_option($opt['slug']), 2), 2);
            ?>
            $<input type="text" name="<?php echo $opt['slug']; ?>" value="<?php echo $value; ?>" />
            <?php
            }
            ?>
          </td>
        </tr>
        <?php
        }
        ?>
      </table>
      <p class="submit">
        <input type="hidden" name="submit" value="true" />
        <input type="submit" class="button-primary" value="<?php _e('Save Changes'); ?>" />
      </p>
    </form>
  </div>
  <?php
}

function mapp_admin_hook_menu()
{
  add_submenu_page('mapp_page', 'MAPP Admin', 'MAPP Admin', 'mapp_admin', 'mapp_admin', 'mapp_admin_render');
}

function mapp_admin_hook_plugins()
{
  global $admin_options;
  foreach($admin_options as $opt) {
    if (get_option($opt['slug']) == false) {
      update_option($opt['slug'], $opt['default']);
    }
  }
}
?>
