<?php
include('../../../wp-blog-header.php');
auth_redirect();
if($wp_version >= '2.6.5') check_admin_referer('surveys_create_edit_survey');
include('wpframe.php');

// I could have put this in the survey_form.php - but the redirect will not work.
if(isset($_REQUEST['submit'])) {
	if($_REQUEST['action'] == 'edit') { //Update goes here
		$wpdb->update(
            $wpdb->prefix . "surveys_survey",
            array(
                'name' => $_REQUEST['name'],
                'description' => $_REQUEST['description'],
                'link_text' => $_REQUEST['link_text'],
                'status' => $_REQUEST['status'],
            ),
            array(
                'ID' => $_REQUEST['survey']
            )
        );
		
		wp_redirect($wpframe_wordpress . '/wp-admin/edit.php?page=surveys/survey.php&message=updated');
	
	} else {
        $wpdb->insert(
            $wpdb->prefix . "surveys_survey",
            array(
                'name' => $_REQUEST['name'],
                'description' => $_REQUEST['description'],
                'link_text' => $_REQUEST['link_text'],
                'status' => $_REQUEST['status'],
            )        
        );
        
		$survey_id = $wpdb->insert_id;
		
		// Create the page for the survey
	 	$pageData = array(
		'post_title' => $_REQUEST[name],
			'comment_status' => 'closed',
		 	'post_content' => "[SURVEYS $survey_id]",
		 	'post_status' => 'publish',
		 	'post_type' => 'page',
		 	'post_author' => $current_user->ID
		 );

	 if($pageID){
		 wp_delete_post( $pageID, true);
	 }
	 
	  $post_id = wp_insert_post($pageData);
	  
	  update_option("___SURVEYS___" . $survey_id, $post_id);
		
		wp_redirect($wpframe_wordpress . '/wp-admin/edit.php?page=surveys/question.php&message=new_survey&survey='.$survey_id);
	}
}
exit;
