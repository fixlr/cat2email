<?php
/*
	Plugin Name: cat2email
	Plugin URI: http://www.skippy.net/blog/plugins
	Description: DISCONTINUED. Sends an email to a specified address on a 
	  per-category basis. Copyright 2005 Scott Merrill, licensed under the GPL.
	Version: 2.1
	Author: Scott Merrill
	Author URI: http://www.skippy.net/
*/

function cat2email_menu() {
	add_management_page('Category To Email', 'Category2Email', 'manage_options', __FILE__, 'c2e_manage');
}

//////////////////
function cat2email ($post_ID = 0) {
global $wpdb, $c2e_token;
if (! $post_ID) { return; }

// don't do anything if we've already run, or if we're editing a post
if (1 === $c2e_token) { return $post_ID; }
// set the token, so that we don't run again
$c2e_token = 1;

$post = & get_post($post_ID);
$user = get_userdata($post->post_author);
$myname = $user->display_name;
$myemailadd = $user->user_email;

$cats = wp_get_post_cats('1', $post_ID);
foreach ($cats as $cat) {
	$c2e_cat = get_option("c2e_$cat");
	if ( ('' != $c2e_cat) && (is_email($c2e_cat)) ) {
		if ('' == $to) {
			$to = $c2e_cat;
		} else {
			$to .= ", " . $c2e_cat;
		}
	}
}

if ('' == $to) {
	// no one to send to!
	return $post_ID;
}

// Set sender details
$headers = "From: \"$myname\" <$myemailadd>\n";
$headers .= "MIME-Version: 1.0\n";

// Set email subject
$subject = '[' . get_settings('blogname') . '] ' . $post->post_title;
$mailtext = '';

if ('html' == get_option('c2e_format')) {
	// To send HTML mail, the Content-type header must be set
	// http://us2.php.net/manual/en/function.mail.php
	$headers .= "Content-type: " . get_bloginfo('html_type') . "; charset=\"" . get_bloginfo('charset') . "\"\n";
	$mailtext = "<html><head><title>$subject</title></head><body>";
	$content = $post->post_content;
	$content = apply_filters('the_content', $content);
	$content = str_replace(']]>', ']]&gt;', $content);
	$mailtext .= $content;
	$mailtext .= "</body></html>";
} else {
	$headers .= "Content-type: text/plain; charset=\"" . get_bloginfo('charset') . "\"\n";
	$mailtext = wordwrap(strip_tags($post->post_content));
}

// And away we go...
wp_mail($to, $subject, $mailtext, $headers);

return $post_ID;
} // end cat2email 


////////////////////
function c2e_manage() {
global $wpdb;

if (isset($_POST['c2e_action'])) {
	$c2e_action = $_POST['c2e_action'];
}

if ('delete' == $c2e_action) {
	c2e_delete();
} elseif ('add' == $c2e_action) {
	c2e_add();
} elseif ('format' == $c2e_action) {
	c2e_format();
}

$c2e_list = array();

$cats = $wpdb->get_col("SELECT cat_ID from $wpdb->categories");

$c2e_format = get_option('c2e_format');
if (FALSE === $c2e_format) {
	$c2e_format = 'plain';
}
// get the list of defined addresses
foreach ($cats as $cat) {
	$foo = get_option("c2e_$cat");
	if ( (FALSE != $foo) && (is_email($foo)) ) {
	 	$c2e_list[$cat] = $foo;
	}
}

echo "<div class='wrap'>\r\n";
echo "<fieldset class='options'><h2>Email Options:</h2>\r\n";
echo "<form method='POST'><input type='hidden' name='c2e_action' value='format'><p align='center'>Generate plaintext or HTML email?<br />\r\n";
echo "<input type='radio' name='c2e_format' value='plain'";
if ('plain' == $c2e_format) {
	echo " checked='checked'";
}
echo " /> Plaintext &nbsp;&nbsp; <input type='radio' name='c2e_format' value='html'";
if ('html' == $c2e_format) {
	echo " checked='checked'";
}
echo " /> HTML<br /><span class='submit'><input type='submit' name='submit' value='submit' /></span></p></form>\r\n";
echo "<strong>Note:</strong> HTML emails will make sure that your posts will display (mostly) correctly in the recipient's mail program; but not every mail program supports this.  Make sure your recipient(s) actually want HTML email before selecting this.</p></fieldset>\r\n";

echo "<fieldset class='options'><h2>Defined Notifications:</h2>\r\n";
if ( (is_array($c2e_list)) && (count($c2e_list) > 0) ) {
	echo '<table width="100%" cellpadding="3" cellspacing="3"><tr>';
	echo '<th scope="col">Category</th><th scope="col">Email</th><th></th></tr>';
	$alternate = 'alternate';
	foreach ($c2e_list  as $key => $value) {
		echo "<tr class='$alternate'>";
		echo "<td width='20%' align='center'>" . get_the_category_by_ID($key) . "</td>";
		echo "<td width='70%' align='center'><a href='mailto:$value'>$value</a></td>";
		echo "<td width='5%' align='center'><form action='' method='POST'><input type='hidden' name='cat' value='$key' /><span class='submit'><input type='submit' class='delete' name='c2e_action' value='delete' /></span></form></td>";
		echo "</tr>\r\n";
		("alternate" == $alternate) ? $alternate = "" : $alternate = "alternate";
	}
}
echo "</table></fieldset>";
echo "<fieldset class='options'><h2>Add New Notification:</h2>\r\n";
echo "<form action='' method='POST'>";
echo "<input type='hidden' name='c2e_action' value='add'>";
dropdown_cats(false, '', 'name', 'asc', false, false, false);
echo "<input type='text' name='email' value='' size='20' />";
echo "<span class='submit'><input type='submit' name='submit' value='submit' /></span></form>";
echo "</fieldset>\r\n";
echo "</div>";
include(ABSPATH . '/wp-admin/admin-footer.php');
// just to be sure
die;
} // end c2e_manage

//////////////////
function c2e_add() {
if ( (isset($_POST['cat'])) && (isset($_POST['email'])) && (is_email($_POST['email'])) ) {
	// first check to see if it exists
	$cat = $_POST['cat'];
	$email = $_POST['email'];
	$foo = get_option("c2e_$cat");
	if (FALSE !== $foo) {
		// now see if it's different
		if ($foo != $email) {
			update_option("c2e_$cat", $email);
		}
	} else {
		add_option("c2e_$cat", $email);
	}
}
// clear the $_POST variable, and go back to manage
$_POST['c2e_action'] = '';
//c2e_manage();
} // c2e_add

/////////////////////
function c2e_delete() {

if (isset($_POST['cat'])) {
	$cat = $_POST['cat'];
	// make sure this exists in the DB
	$foo = get_option("c2e_$cat");
	if (FALSE != $foo) {
		update_option("c2e_$cat", '');
	}
}
$_POST['c2e_action'] = '';
//c2e_manage();
} // c2e_delete

/////////////////////
function c2e_format() {
if (isset($_POST['c2e_format'])) {
	update_option('c2e_format', $_POST['c2e_format']);
}
$_POST['c2e_action'] = '';
} // c2e_format

function c2e_edit($ID = 0) {
	if (0 == $ID) { return; }

	global $c2e_token;
	$c2e_token = 1;
	return $ID;
}

/////////////////////
// main program block
global $c2e_token;
add_action('admin_menu', 'cat2email_menu');
add_action('edit_post', 'c2e_edit');
add_action('publish_post', 'cat2email');
add_action('private_to_published', 'cat2email');

?>