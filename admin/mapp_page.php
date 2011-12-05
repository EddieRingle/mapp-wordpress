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

global $mapp_db_version;
$mapp_db_version = "0.1alpha1";

function mapp_page_dbinstall()
{
  global $wpdb;
  global $mapp_db_version;
  $table_name = $wpdb->prefix . "mapp_users";

  $installed_ver = get_option("mapp_db_version");
  if ($installed_ver != $mapp_db_version) {
    $sql = 'CREATE TABLE ' . $table_name . ' (
              id mediumint(9) NOT NULL AUTO_INCREMENT,
              wpid mediumint(9) NOT NULL,
              login varchar(255) NOT NULL,
              paypal varchar(255) NULL,
              pay_per_word bit NULL,
              payout_to_date DECIMAL(12, 2) NULL,
              UNIQUE KEY id (id)
            );';
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    update_option("mapp_db_version", $mapp_db_version);
  }
}

function mapp_page_render()
{
  global $wpdb;
  $messages = array();
  $current_user = wp_get_current_user();
  $current_mapp_user = null;
  if (isset($_GET['mapp_target_user'])) {
    $current_mapp_user = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "mapp_users WHERE login = '%s'", $_GET['mapp_target_user']));
    if ($current_mapp_user == null) {
      /* This really shouldn't happen (I don't think, anyway) */
      wp_die("\$_GET['mapp_target_user'] should hold a valid login name. Hmm...");
    }
  } else {
    $current_mapp_user = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "mapp_users WHERE login = '%s'", $current_user->data->user_login));
    if ($current_mapp_user == null) {
      /* If currently logged in user has yet to configure MAPP, create a table entry for him now. */
      $wpdb->insert(
        $wpdb->prefix . 'mapp_users',
        array(
          'wpid' => $current_user->ID,
          'login' => $current_user->data->user_login,
          /* Pay per word defaults to false until we add an option for admins to pick */
          'pay_per_word' => 0,
          'payout_to_date' => 0.0
        ),
        array('%d', '%s', '%d', '%f')
      );
      $current_mapp_user = $wpdb->get_row("SELECT * FROM " . $wpdb->prefix . "mapp_users WHERE id = " . $wpdb->insert_id);
    }
  }
  /* Load user's posts from database in a given time range (defaults to past 2 weeks) */
  $from_date = null;
  $to_date = null;
  if (isset($_GET['to_date'])) {
    $to_date = date('Y/m/d', strtotime($_GET['to_date']));
  }
  if (isset($_GET['from_date'])) {
    $from_date = date('Y/m/d', strtotime($_GET['from_date']));
  }
  if ($from_date == null && $to_date != null) {
    $from_date = date('Y/m/d', strtotime('-2011 years'));
  } else if ($to_date == null && $from_date != null) {
    $to_date = date('Y/m/d', strtotime('now'));
  } else if ($from_date == null && $to_date == null) {
    $from_date = date('Y/m/d', strtotime('-14 days'));
    $to_date = date('Y/m/d', strtotime('now'));
  }
  $sql = 'SELECT * FROM ' . $wpdb->posts
    . ' WHERE post_author = ' . $current_mapp_user->wpid
    . ' AND post_status = "publish"'
    . ' AND post_date BETWEEN \'' . $from_date
    . '\' AND \'' . date('Y/m/d', strtotime($to_date . ' +1 day')) . '\''
    . ' AND post_type = \'post\''
    . ' ORDER BY post_date DESC';
  $user_posts = $wpdb->get_results($sql);
  $user_has_posts = $wpdb->num_rows > 0;

  /* Determine current user's roles */
  $is_admin = current_user_can('administrator');
  $is_accountant = current_user_can('mapp_accountant');

  if (isset($_POST) && isset($_POST['paypal_email'])) {
    check_admin_referer('mapp_page_set_paypal', 'mapp_nonce');
    if (!is_email($_POST['paypal_email'])) {
      array_push($messages, array(
        'class' => 'error',
        'content' => 'You did not enter a valid email address.'
      ));
    } else {
      /* Don't update database if the email is the same */
      if ($_POST['paypal_email'] == $current_mapp_user->paypal) {
        array_push($messages, array(
          'class' => 'updated',
          'content' => 'Identical email was entered. Database not updated.'
        ));
      } else if ($wpdb->update($wpdb->prefix . 'mapp_users',
        array('paypal' => $_POST['paypal_email']),
        array('login' => $current_mapp_user->login),
        array('%s'), array('%s')
      ) != false) {
        $current_mapp_user->paypal = $_POST['paypal_email'];
        array_push($messages, array(
          'class' => 'updated',
          'content' => 'PayPal email updated successfully.'
        ));
      } else {
        array_push($messages, array(
          'class' => 'error',
          'content' => 'Failed to update PayPal email.'
        ));
      }
    }
  }
  ?>
  <?php
  /* Show MAPP writer dropdown if the current user is an admin or an accountant */
  if ($is_admin || $is_accountant) {
    $mapp_users = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "mapp_users");
  }
  ?>
  <style type="text/css">
    h3.greeting {
      text-transform:capitalize;
    }
    span#post-total {
      display: block;
      position: relative;
      right: 4.75em;
      top: 1em;
      font-size: 1.5em;
      text-align: right;
    }
  </style>
  <div class="wrap">
    <h2>MAPP for WordPress</h2>
    <?php
    /* Display messages */
    foreach ($messages as $msg) {
    ?>
      <div id="message" class="<?php echo $msg['class']; ?>"><p><?php echo $msg['content']; ?></p></div>
    <?php
    }
    ?>
    <p>Thanks for using MAPP for WordPress!</p>
    <?php
    if ($is_admin || $is_accountant) {
      $no_users = false;
      if ($wpdb->num_rows < 1) {
        $no_users = true;
      }
    ?>
    <h3>Select a Writer to Manage</h3>
    <p>The currently selected writer is <?php echo $current_mapp_user->login; ?>.</p>
    <?php
      if ($no_users == false) {
    ?>
    <form name="mapp_user_select_form" method="get">
      <input type="hidden" name="page" value="mapp_page" />
      <select name="mapp_target_user">
      <?php
      foreach ($mapp_users as $mapp_user) {
        if ($mapp_user->login == $current_mapp_user->login) {
          echo '<option selected>';
        } else if ($mapp_user->login == $current_user->data->user_login) {
          echo '<option selected>';
        } else {
          echo '<option>';
        }
        echo $mapp_user->login, '</option>';
      }
      ?>
      </select>
      <input type="submit" value="Go" />
    </form>
    <?php
      } else {
      ?>
      <p>None of your blog's users have configured their accounts for MAPP yet, direct them to this page to do so.</p>
      <?php
      }
    }
    ?>
    <h3>Payment Settings</h3>
    <?php
    if ($current_mapp_user == null || $current_mapp_user->paypal == "") {
    ?>
    <p style="font-weight: bold;">ATTENTION! You have not set a PayPal email yet. You will not be able to be paid until you do so.</p>
    <?php
    }
    ?>
    <p>MAPP uses PayPal to send writers payments. Please enter your PayPal email in the box below to make sure you get paid promptly.
      <form name="mapp_user_paypal_form" method="post">
        <?php wp_nonce_field('mapp_page_set_paypal', 'mapp_nonce'); ?>
        <input type="hidden" name="page" value="mapp_page" />
        <input type="hidden" name="mapp_target_user" value="<?php echo $current_mapp_user->login; ?>" />
        <label for="paypal_email">PayPal email: </label>
        <input type="text" name="paypal_email" value="<?php echo $current_mapp_user->paypal; ?>" />
        <input type="submit" value="Save" />
      </form>
    </p>
    <h2>Writer Data</h2>
    <p>Select a date range to view post data:</p>
    <form name="mapp_user_post_range_form" method="get">
      <input type="hidden" name="page" value="mapp_page" />
      <input type="hidden" name="mapp_target_user" value="<?php echo $current_mapp_user->login; ?>" />
      <label for="from_date">From </label>
      <input type="text" name="from_date" value="<?php echo ($from_date == null) ? date("Y/m/d", strtotime('-14 days')) : $from_date; ?>" />
      <label for="to_date"> to </label>
      <input type="text" name="to_date" value="<?php echo ($to_date == null) ? date("Y/m/d", strtotime('now')) : $to_date; ?>" />
      <input type="submit" value="Go" />
    </form>
    <br/>
    <table class="wp-list-table widefat fixed" cellspacing="0">
      <thead>
        <tr>
          <th scope="col">Title</th>
          <th scope="col">Date</th>
          <th scope="col">Word Count</th>
          <th scope="col">Cost @ $0.25/word</th>
        </tr>
      </thead>
      <tbody id="the-list">
        <?php
        $total_word_count = 0;
        foreach ($user_posts as $post) {
        $word_count = str_word_count(strip_tags($post->post_content), 0);
        $total_word_count += $word_count;
        ?>
        <tr class="post type-post hentry">
          <td class="post-title page-title column-title">
            <strong><?php echo $post->post_title; ?></strong>
          </td>
          <td class="date column-date">
            <strong><?php echo date('Y/m/d', strtotime($post->post_date)); ?>
          </td>
          <td class="word-count column-words">
            <strong><?php echo $word_count; ?></strong>
          </td>
          <td class="cost column-cost">
            <strong>$<?php echo number_format(round($word_count * 0.25, 2), 2); ?></strong>
          </td>
        </tr>
        <?php
        }
        ?>
      </tbody>
      <tfoot style="background-color: #ECECEC; background-image: none; border-top-color:#ECECEC;">
        <tr>
          <th scope="col" style="border-top: none; background-color: #ECECEC; background-image: none;">&nbsp;</th>
          <th scope="col" style="border-top: none; background-color: #ECECEC; background-image: none;">&nbsp;</th>
          <th scope="col" style="border-top: none; background-color: #ECECEC; background-image: none;"><strong>Total Cost:</strong></th>
          <th scope="col" style="border-top: none; background-color: #ECECEC; background-image: none;"><strong>$<?php echo number_format(round($total_word_count * 0.25, 2), 2); ?></strong></th>
        </tr>
      </tfoot>
      <tfoot>
        <tr>
          <th scope="col">Title</th>
          <th scope="col">Date</th>
          <th scope="col">Word Count</th>
          <th scope="col">Cost @ $0.25/word</th>
        </tr>
      </tfoot>
    </table>
    <?php
    if ($is_admin || $is_accountant) {
    ?>
    <h3>Pay this Writer</h3>
    <p>The button below will take you to PayPal in order to pay this writer for the amount totalling <strong>$<?php echo number_format(round($total_word_count * 0.25, 2), 2); ?></strong>.</p>
    <form action="https://www.paypal.com/cgi-bin/webscr" method="post">
      <input type="hidden" name="cmd" value="_xclick" />
      <input type="hidden" name="business" value="<?php echo $current_mapp_user->paypal; ?>" />
      <input type="hidden" name="item_name" value="Writing for <?php echo get_bloginfo('name'); ?>" />
      <input type="hidden" name="amount" value="<?php echo number_format(round($total_word_count * 0.25, 2), 2); ?>" />
      <input type="hidden" name="lc" value="US" />
      <input type="hidden" name="no_note" value="0" />
      <input type="hidden" name="no_shipping" value="0" />
      <input type="hidden" name="currency_code" value="USD" />
      <input type="submit" name="submit" class="button-primary" value="Pay Now" <?php if ($current_mapp_user->paypal == null): echo 'disabled'; endif; ?> />
      <?php
        if ($current_mapp_user->paypal == null) {
          echo '<strong>This user does not have a PayPal email set, so you cannot pay them. Congrats I guess.</strong>';
        }
      ?>
    </form>
    <?php
    }
    ?>
  </div>
  <?php
}

function mapp_page_hook_menu()
{
  add_menu_page("MAPP for WordPress", "MAPP for WordPress", "edit_posts", "mapp_page", "mapp_page_render");
}

function mapp_page_hook_plugins()
{
  global $mapp_db_version;
  if (get_site_option('mapp_db_version') != $mapp_db_version) {
    mapp_page_dbinstall();
  }
}
?>
