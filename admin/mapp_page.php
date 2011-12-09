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
              fb_domain varchar(255) NULL,
              fb_token varchar(32) NULL,
              pay_per_what varchar(32) NOT NULL,
              cost_per_word DECIMAL(12, 2) NULL,
              cost_per_post DECIMAL(12, 2) NULL,
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
          'pay_per_what' => 'default',
          'payout_to_date' => 0.0
        ),
        array('%d', '%s', '%s', '%f')
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
  } elseif ($to_date == null && $from_date != null) {
    $to_date = date('Y/m/d', strtotime('now'));
  } elseif ($from_date == null && $to_date == null) {
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
  $is_self = $current_mapp_user->wpid == $current_user->ID;

  if (isset($_POST)) {
    if (isset($_POST['freshbooks_domain']) && isset($_POST['freshbooks_token'])) {
      check_admin_referer('mapp_page_set_freshbooks', 'mapp_nonce');
      /* FreshBook API Tokens are 32 characters in length */
      if (strlen($_POST['freshbooks_token']) != 32) {
        array_push($messages, array(
          'class' => 'error',
          'content' => 'FreshBook API Tokens must be 32 characters in length.'
        ));
      } else {
        FreshBooksRequest::init($_POST['freshbooks_domain'], $_POST['freshbooks_token']);
        $fbr = new FreshBooksRequest('client.list');
        $fbr->request();
        if (!$fbr->success()) {
          array_push($messages, array(
            'class' => 'error',
            'content' => 'Error connecting to FreshBooks, double check your domain/token.'
          ));
        } else {
          if ($_POST['freshbooks_domain'] == $current_mapp_user->fb_domain
            && $_POST['freshbooks_token'] == $current_mapp_user->fb_token) {
            array_push($messages, array(
              'class' => 'updated',
              'content' => 'Identical domain and token entered, database was not updated.'
            ));
          } elseif ($wpdb->update($wpdb->prefix . 'mapp_users',
            array(
              'fb_domain' => $_POST['freshbooks_domain'],
              'fb_token' => $_POST['freshbooks_token']
            ),
            array('login' => $current_mapp_user->login),
            array('%s', '%s'), array('%s')) != false) {
            $current_mapp_user->fb_domain = $_POST['freshbooks_domain'];
            $current_mapp_user->fb_token = $_POST['freshbooks_token'];
            array_push($messages, array(
              'class' => 'updated',
              'content' => 'Successfully updated FreshBooks information.'
            ));
          } else {
            array_push($messages, array(
              'class' => 'error',
              'content' => 'Failed to update FreshBooks information.'
            ));
          }
        }
      }
    }
    if (isset($_POST['pay_per_what'])) {
      check_admin_referer('mapp_page_payment_options', 'mapp_nonce');
      $cpp = -1.0;
      $cpw = -1.0;
      if ($_POST['cost_per_post_opt'] == 'custom' && isset($_POST['cost_per_post'])) {
        $cpp = number_format(round($_POST['cost_per_post'], 2), 2);
      }
      if ($_POST['cost_per_word_opt'] == 'custom' && isset($_POST['cost_per_word'])) {
        $cpw = number_format(round($_POST['cost_per_word'], 2), 2);
      }
      if ($wpdb->update($wpdb->prefix . 'mapp_users',
        array(
          'pay_per_what' => $_POST['pay_per_what'],
          'cost_per_post' => $cpp,
          'cost_per_word' => $cpw
        ),
        array('login' => $current_mapp_user->login),
        array('%s', '%f', '%f'), array('%s')) != false) {
        $current_mapp_user->pay_per_what = $_POST['pay_per_what'];
        $current_mapp_user->cost_per_post = $cpp;
        $current_mapp_user->cost_per_word = $cpw;
        array_push($messages, array(
          'class' => 'updated',
          'content' => 'Payment options updated successfully.'
        ));
      } else {
        array_push($messages, array(
          'class' => 'error',
          'content' => 'Failed to update payment options.'
        ));
      }
    }
  }

  /* Load FreshBooks credentials */
  $employer_id = -1;
  if ($current_mapp_user->fb_domain != null && $current_mapp_user->fb_token != null) {
    FreshBooksRequest::init($current_mapp_user->fb_domain, $current_mapp_user->fb_token);
    $fbr = new FreshBooksRequest('client.list');
    $fbr->post(array(
      'email' => get_option('mapp_invoice_email')
    ));
    $fbr->request();
    $fb_resp = $fbr->getResponse();
    if ($fbr->success()) {
      if (isset($fb_resp['clients']['client'])) {
        /* Check if we only got response */
        if (isset($fb_resp['clients']['client']['client_id'])) {
          $employer_id = $fb_resp['clients']['client']['client_id'];
        } else { /* ... or multiple */
          $employer_id = $fb_resp['clients']['client'][0]['client_id'];
        }
      } else {
        /* Make the user create a new client */
        array_push($messages, array(
          'class' => 'error',
          'content' => 'Please log in to your FreshBooks account and create a new client with the email: <b>' . get_option('mapp_invoice_email') . '</b>'
        ));
      }
    }
  }

  /* Get rates from options */
  $default_post_flat_rate = number_format(round(get_option('mapp_post_flat_rate'), 2), 2);
  $default_post_word_rate = number_format(round(get_option('mapp_post_word_rate'), 2), 2);
  $post_flat_rate = $default_post_flat_rate;
  $post_word_rate = $default_post_word_rate;
  /* If set in DB, get specific prices set by the admin */
  if ($current_mapp_user->cost_per_post != NULL
      && $current_mapp_user->cost_per_post > 0.0) {
    $post_flat_rate = number_format(round($current_mapp_user->cost_per_post, 2), 2);
  }
  if ($current_mapp_user->cost_per_word != NULL
      && $current_mapp_user->cost_per_word > 0.0) {
    $post_word_rate = number_format(round($current_mapp_user->cost_per_word, 2), 2);
  }

  $default_pay_per_what = get_option('mapp_default_payment_rate');
  $pay_per_what = $current_mapp_user->pay_per_what;
  if ($pay_per_what == NULL) {
    $pay_per_what = 'default';
  }
  $are_pay_per_word = $pay_per_what == 'word'
                                      || ($pay_per_what == 'default'
                                          && $default_pay_per_what == 'word');
  $are_pay_per_post = $pay_per_what == 'post'
                                      || ($pay_per_what == 'default'
                                          && $default_pay_per_what == 'post');

  /* Check if we're generating an invoice */
  if (isset($_GET['invoice']) && $employer_id > -1 && $user_has_posts) {
    check_admin_referer('mapp_user_generate_invoice', 'mapp_nonce');
    $fbr = new FreshBooksRequest('invoice.create');
    $lines = array();
    $total_word_count = 0;
    $total_post_count = 0;
    foreach ($user_posts as $post) {
      $word_count = str_word_count(strip_tags($post->post_content), 0);
      $total_word_count += $word_count;
      $total_post_count++;
      $line_name = '['.date('Y/m/d', strtotime($post->post_date)).'] '.$post->post_title;
      if ($are_pay_per_post) {
        array_push($lines, array(
          'name' => $line_name,
          'description' => $word_count.' words - Pay rate is $'.$post_flat_rate.'/post',
          'unit_cost' => $post_flat_rate,
          'quantity' => 1
        ));
      } else {
        array_push($lines, array(
          'name' => $line_name,
          'description' => $word_count.' words - Pay rate is $'.$post_word_rate.'/word',
          'unit_cost' => number_format(round($post_word_rate * $word_count, 2), 2),
          'quantity' => 1
        ));
      }
    }
    $total_post_cost = number_format(round($total_post_count * $post_flat_rate, 2), 2);
    $total_word_cost = number_format(round($total_word_count * $post_word_rate, 2), 2);
    $fbr->post(array(
      'invoice' => array(
        'client_id' => $employer_id,
        'lines' => array(
          'line' => $lines
        )
      )
    ));
    $fbr->request();
    if ($fbr->success()) {
      $resp = $fbr->getResponse();
      array_push($messages, array(
        'class' => 'updated',
        'content' => 'Invoice generated. <a href="https://'.$current_mapp_user->fb_domain.'.freshbooks.com/invoices/'.$resp['invoice_id'].'">Click here</a> to view it.'
      ));
    } else {
      array_push($messages, array(
        'class' => 'error',
        'content' => 'Error generating the invoice.'
      ));
    }
  }
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
    div.admin-function {
      background-color: #333;
      color: #ccc;
      padding: 15px;
      margin: 10px;
      border-radius: 10px;
      -webkit-border-radius: 10px;
      -moz-border-radius: 10px;
    }
    div.admin-function h3 {
      margin: 0;
    }
  </style>
  <div class="wrap">
    <h2>MAPP Dashboard</h2>
    <?php
    /* Display messages */
    foreach ($messages as $msg) {
    ?>
      <div id="message" class="<?php echo $msg['class']; ?>"><p><?php echo $msg['content']; ?></p></div>
    <?php
    }
    ?>
    <?php
    if ($is_admin || $is_accountant) {
      $no_users = false;
      if ($wpdb->num_rows < 1) {
        $no_users = true;
      }
    ?>
    <div class="admin-function">
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
          } elseif ($mapp_user->login == $current_user->data->user_login) {
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
    </div>
    <?php
    if ($is_admin) {
    ?>
    <div class="admin-function">
      <h3>Payment Options</h3>
      <p>
      <form name="mapp_user_payment_options_form" method="post">
        <?php wp_nonce_field('mapp_page_payment_options', 'mapp_nonce'); ?>
        <input type="hidden" name="page" value="mapp_page" />
        <input type="hidden" name="mapp_target_user" value="<?php echo $current_mapp_user->login; ?>" />
        <p>
        <label for="pay_per_what">Pay this writer by the:</label>
        <input type="radio" name="pay_per_what" value="default" <?php echo ($pay_per_what == 'default') ? 'checked' : ''; ?>>Default (Per-<?php echo $default_pay_per_what; ?>)</input>
        <input type="radio" name="pay_per_what" value="post" <?php echo ($pay_per_what == 'post') ? 'checked' : ''; ?>>Post</input>
        <input type="radio" name="pay_per_what" value="word" <?php echo ($pay_per_what == 'word') ? 'checked' : ''; ?>>Word</input>
        </p>
        <label for="cost_per_post">Pay rate per post: </label><br/>
        <p>
        <input type="radio" name="cost_per_post_opt" value="default" <?php echo ($current_mapp_user->cost_per_post == NULL || $current_mapp_user->cost_per_post < 0.0) ? 'checked' : ''; ?> />Default ($<?php echo $default_post_flat_rate; ?>/post)<br/>
        <input type="radio" name="cost_per_post_opt" value="custom" <?php echo ($current_mapp_user->cost_per_post != NULL && $current_mapp_user->cost_per_post >= 0.0) ? 'checked' : ''; ?> />Custom rate: $
        <input type="text" name="cost_per_post" size="4" value="<?php echo $post_flat_rate; ?>" />/post
        </p>
        <label for="cost_per_word">Pay rate per word: </label><br/>
        <p>
        <input type="radio" name="cost_per_word_opt" value="default" <?php echo ($current_mapp_user->cost_per_word == NULL || $current_mapp_user->cost_per_word < 0.0) ? 'checked' : ''; ?> />Default ($<?php echo $default_post_word_rate; ?>/word)<br/>
        <input type="radio" name="cost_per_word_opt" value="custom" <?php echo ($current_mapp_user->cost_per_word != NULL && $current_mapp_user->cost_per_word >= 0.0) ? 'checked' : ''; ?> />Custom rate: $
        <input type="text" name="cost_per_word" size="4" value="<?php echo $post_word_rate; ?>" />/word
        </p>
        <input type="submit" value="Save" />
      </form>
      </p>
    </div>
    <?php
    }
    ?>
    <?php
    if ($is_self) {
    ?>
    <h3>FreshBooks Information</h3>
    <?php
      if ($current_mapp_user == null || $current_mapp_user->fb_domain == "" || $current_mapp_user->fb_token == "") {
    ?>
    <p style="font-weight: bold;">ATTENTION! You have not set a FreshBooks domain & token yet. You will not be able to be paid until you do so.</p>
    <?php
      }
    ?>
    <p>MAPP uses FreshBooks to invoice the blog owner for payment. Please enter your FreshBooks domain and API token in the box below to make sure you get paid promptly.
      <form name="mapp_user_freshbooks_form" method="post">
        <?php wp_nonce_field('mapp_page_set_freshbooks', 'mapp_nonce'); ?>
        <input type="hidden" name="page" value="mapp_page" />
        <input type="hidden" name="mapp_target_user" value="<?php echo $current_mapp_user->login; ?>" />
        <label for="freshbooks_domain">Domain:</label><br/>
        <input type="text" name="freshbooks_domain" id="freshbooks_domain" value="<?php echo $current_mapp_user->fb_domain; ?>" /><br/>
        <label for="freshbooks_token">Token:</label><br/>
        <input type="text" name="freshbooks_token" id="freshbooks_token" value="<?php echo $current_mapp_user->fb_token; ?>" /><br/>
        <input type="submit" value="Save" />
      </form>
    </p>
    <?php
    }
    ?>
    <h3>Writer Data</h3>
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
          <?php
          if ($are_pay_per_word) {
          ?>
          <th scope="col">Cost @ $<?php echo $post_word_rate; ?>/word</th>
          <?php
          } elseif ($are_pay_per_post) {
          ?>
          <th scope="col">Cost @ $<?php echo $post_flat_rate; ?>/post</th>
          <?php
          }
          ?>
        </tr>
      </thead>
      <tbody id="the-list">
        <?php
        $total_word_count = 0;
        $total_post_count = 0;
        foreach ($user_posts as $post) {
        $word_count = str_word_count(strip_tags($post->post_content), 0);
        $total_word_count += $word_count;
        $total_post_count++;
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
          <?php
          if ($are_pay_per_word) {
          ?>
          <td class="word-cost column-word-cost">
            <strong>$<?php echo number_format(round($word_count * $post_word_rate, 2), 2); ?></strong>
          </td>
          <?php
          } elseif ($are_pay_per_post) {
          ?>
          <td class="flat-cost column-flat-cost">
            <strong>$<?php echo $post_flat_rate; ?></strong>
          </td>
          <?php
          }
          ?>
        </tr>
        <?php
        }
        $total_post_cost = number_format(round($total_post_count * $post_flat_rate, 2), 2);
        $total_word_cost = number_format(round($total_word_count * $post_word_rate, 2), 2);
        ?>
      </tbody>
      <tfoot style="background-color: #ECECEC; background-image: none; border-top-color:#ECECEC;">
        <tr>
          <th scope="col" style="border-top: none; background-color: #ECECEC; background-image: none;"><strong>Totals:</strong></th>
          <th scope="col" style="border-top: none; background-color: #ECECEC; background-image: none;"><strong><?php echo $total_post_count, ' posts'; ?></strong></th>
          <th scope="col" style="border-top: none; background-color: #ECECEC; background-image: none;"><strong><?php echo $total_word_count, ' words'; ?></strong></th>
          <?php
          if ($are_pay_per_word) {
          ?>
          <th scope="col" style="border-top: none; background-color: #ECECEC; background-image: none;"><strong>$<?php echo $total_word_cost; ?></strong></th>
          <?php
          } elseif ($are_pay_per_post) {
          ?>
          <th scope="col" style="border-top: none; background-color: #ECECEC; background-image: none;"><strong>$<?php echo $total_post_cost; ?></strong></th>
          <?php
          }
          ?>
        </tr>
      </tfoot>
      <tfoot>
        <tr>
          <th scope="col">Title</th>
          <th scope="col">Date</th>
          <th scope="col">Word Count</th>
          <?php
          if ($are_pay_per_word) {
          ?>
          <th scope="col">Cost @ $<?php echo $post_word_rate; ?>/word</th>
          <?php
          } elseif ($are_pay_per_post) {
          ?>
          <th scope="col">Cost @ $<?php echo $post_flat_rate; ?>/post</th>
          <?php
          }
          ?>
        </tr>
      </tfoot>
    </table>
    <?php
    if ($employer_id > -1) {
    ?>
    <h3>Generate an Invoice</h3>
    <p>The button below will generate a FreshBooks invoice which you will be able to send to your employer.</p>
    <form name="mapp_user_generate_invoice_form" method="get">
      <?php wp_nonce_field('mapp_user_generate_invoice', 'mapp_nonce'); ?>
      <input type="hidden" name="page" value="mapp_page" />
      <input type="hidden" name="mapp_target_user" value="<?php echo $current_mapp_user->login; ?>" />
      <?php
      if (isset($_GET['from_date'])) {
      ?>
      <input type="hidden" name="from_date" value="<?php echo $_GET['from_date']; ?>" />
      <?php
      }
      if (isset($_GET['to_date'])) {
      ?>
      <input type="hidden" name="to_date" value="<?php echo $_GET['to_date']; ?>" />
      <?php
      }
      ?>
      <input type="hidden" name="invoice" value="true" />
      <input type="submit" class="button-primary" value="Generate" />
    </form>
    <?php
    }
    ?>
  </div>
  <?php
}

function mapp_page_hook_menu()
{
  add_menu_page("MAPP Dashboard", "MAPP Dashboard", "edit_posts", "mapp_page", "mapp_page_render");
}

function mapp_page_hook_plugins()
{
  global $mapp_db_version;
  if (get_site_option('mapp_db_version') != $mapp_db_version) {
    mapp_page_dbinstall();
  }
}
?>
