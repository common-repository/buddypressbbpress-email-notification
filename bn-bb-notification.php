<?php
/**
 * Plugin Name: Forum Post Notification
 * Plugin Description: Sends an Notification email if there's a new post to a topic. ASSUMES that bbpress is installed (with table bb_posts) along with BuddyPress!
 * Author: Brent Layman
 * Author URI: http://buglenotes.com
 * Plugin URI: http://buglenotes.com
 * Version: 1.0
 */
 
// email all group members when a new topic is created in that group
function bn_nofification_new_topic($mygroup_obj, $mytopic){
	
	$user_link = bp_core_get_userlink($mytopic->topic_poster);
 	$group_link = bp_group_permalink( $mygroup_obj, false );

	$mygroup = new BP_Groups_Group( $mygroup_obj, false, true ); // get group slug and group name
	
	$mytopic_link = $group_link . $mygroup->slug .'/forum/topic/' . $mytopic->topic_slug;
	$mygroup_link = $group_link . $mygroup->slug;
	
	
	// get the post text
	$getthepost =  bp_forums_get_post( $mytopic->topic_last_post_id);
	$getpost_text = $getthepost->post_text;
	$myposttext = $getpost_text; // tmp fix for now.  need to add this so myposttext is populated.  Brent
	

	// Always set content-type when sending HTML email
	$headers = "MIME-Version: 1.0" . "\r\n";
	$headers .= "Content-type:text/html;charset=iso-8859-1" . "\r\n";
	
	$subject = '[' . get_blog_option( 1, 'blogname' ) . '] ' . sprintf( __( 'New Topic Created in the Group: %s', 'buddypress' ), stripslashes($mygroup->name) );
	
	$message = sprintf( __( '%s has just created a new topic  "<a href="%s">%s</a>" in the group <a href="%s">%s Group</a>
	<br />----------------------------<br />%s
	<br />----------------------------
	<br />	To <b>VIEW</b> to this topic and ADD to the conversation click: <a href="%s">%s</a>
	
	', 'buddypress' ), $user_link, $mytopic_link, $mytopic->topic_title, $mygroup_link, $mygroup->name,$myposttext,$mytopic_link,$mytopic->topic_title);
	
	foreach ( $mygroup->user_dataset as $user ) {
		
		$ud = get_userdata( $user->user_id );

		if ($ud->ID != $mytopic->topic_poster) { // don't email user that posted topic
			// Set up and send the message
			$to = $ud->user_email;
			if ($message) { wp_mail( $to, $subject, stripslashes($message), $headers); }
			unset( $to );
		}
	}


}
 

// when a reply is posted to a topic, email anyone who has a post in that topic
function bn_notification_new_post($groupid, $mypostarray) {
	global $wpdb; 
	
	
 	$group_link = bp_group_permalink( $groupid, false );

	$mygroup = new BP_Groups_Group( $groupid, false, true ); // get group slug and group name
	
	//$getthepost =  bp_forums_get_post($mypostarray);
	$mypostarray =  bp_forums_get_post($mypostarray);
	
	$user_link = bp_core_get_userlink($mypostarray->poster_id);
	
	
	
	$mygroup_name = $mygroup->name;

	$mytopic_id = $mypostarray->topic_id;
	$myforum_id = $mypostarray->forum_id;
	$mypost_id = $mypostarray->post_id;
	$mypost_poster_id = $mypostarray->poster_id;
	
	$mytopicarray = bp_forums_get_topic_details( $mypostarray->topic_id);
					
	$topic_slug = $mytopicarray->topic_slug; 
	$topic_title = $mytopicarray->topic_title;
	$forum_name = $mygroup->name;
	$group_id = $mygroup->id;
	$group_slug = $mygroup->slug;
	$getpost_text = $mypostarray->post_text; 
	$mytopic_link = $group_link . $mygroup->slug .'/forum/topic/' . $topic_slug;

	$myposttext = $getpost_text; // this will probably be nasty looking
	$mygrouplink = '<a href="' . $group_link . $group_slug . '">' . $forum_name . '</a>';

	// Always set content-type when sending HTML email
	$headers = "MIME-Version: 1.0" . "\r\n";
	$headers .= "Content-type:text/html;charset=iso-8859-1" . "\r\n";
	
	$subject = '[' . get_blog_option( 1, 'blogname' ) . '] ' . sprintf( __( 'New post on topic: %s', 'buddypress' ), stripslashes($topic_title) );
	$message = sprintf( __( '%s just posted a new reply on the topic  "<a href="%s">%s</a>"  in the forum %s:
<br /><br />
---------------------<br />

%s

<br />---------------------
<br /><br />

To <b>REPLY</b> to this post click: <a href="%s#bnreply">%s</a>


', 'buddypress' ), $user_link, stripslashes($mytopic_link), stripslashes($topic_title), $mygrouplink, stripslashes($myposttext), stripslashes($mytopic_link), stripslashes($topic_title));

	// now that the email is ready, get a list of users that have posted in the topic.  The current poster will be skipped.
	
	// does the poster want a cc email
	$ccmail = ($_POST['ccme'] == "copyme") ? '' : 'AND poster_id != ' . $mypost_poster_id;
	
	$thequery = "SELECT user_email 
				 FROM $wpdb->users, bb_posts 
				 WHERE user_status=0 AND
						topic_id = $mytopic_id AND
						forum_id = $myforum_id AND
						poster_id = ID " . $ccmail . "
				GROUP BY poster_id";
	
	$all_users = $wpdb->get_results($thequery);
	
	foreach ($all_users as $userdata) :
		// Send it
		$to = $userdata->user_email;
		if ($message) { wp_mail( $to, $subject, $message, $headers); }
		unset( $to );
	endforeach;
}

// ask if the user would like to get a cc of the email
function bn_cc_new_post(){
	$ccme = '<p><a name="bnreply"></a><input name="ccme" type="checkbox" value="copyme" unchecked> Please send me a copy of my reply in an email</p>';
	echo $ccme;
}
 
add_action( 'groups_new_forum_topic_post', 'bn_notification_new_post', 12, 2 ); // priority set to 12 to make sure topic has been created
add_action( 'groups_new_forum_topic', 'bn_nofification_new_topic', 12, 2 ); // priority set to 12 to make sure reply has been saved
add_action( 'groups_forum_new_reply_before', 'bn_cc_new_post', 1, 0);

?>