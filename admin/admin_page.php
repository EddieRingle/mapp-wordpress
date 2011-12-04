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

function mapp_admin_page_render()
{
  ?>
  <div class="wrap">
    <h2>MAPP for WordPress</h2>
    <p>Thanks for using MAPP for WordPress!</p>
    <h2>Pseudocode</h2>
    <ol>
      <li>Greet the user
      <li>IF user is an admin/accountant, offer a selection of post contributors to select from here, else default to currently logged in user's stats
      <li>IF user is an admin, show options to configure whether the selected user is paid per word or per post
      <li>Show a field for the user to enter in a PayPal email for payments
      <li>Display a list of posts made in the selected time range, list items should include words written for that post, along with the cost of the post. Above this should be a time range field
      <li>Display calculated total cost of the selected user's posts for the selected time range
      <li>IF user is an admin/accountant, show a "Pay now" button, but only if the selected user has a PayPal email set, of course.
    </ol>
  </div>
  <?php
}

function mapp_admin_page_hook()
{
  add_menu_page("MAPP for WordPress", "MAPP for WordPress", "edit_posts", "mapp_admin", "mapp_admin_page_render");
}
?>
