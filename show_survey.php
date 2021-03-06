<?php
//global $default_answers;
include('wpframe.php');
if(!session_id()){	session_start();}
wpframe_stop_direct_call(__FILE__);



// Pass in default_answers when calling surveys_universal_shortcode method (added for Genesis hidden fields)
if(empty($default_answers)){
	$default_answers = array();
}

function isAnswerSelected($question, $ans, $type = null){
	if(!$ans |! $_POST){
		return '';
	}
	
	if(!isset($_POST['answer-' . $question->ID])){
		return '';
	}
	
	if($type == 'user'){
		if(in_array('user-answer', $_POST['answer-' . $question->ID])){
			return "checked='checked'";
		}
	}else{
		if(in_array($ans->ID, $_POST['answer-' . $question->ID])){
			return "checked='checked'";
		}
	}
	return '';
}

function getUserAnswerValue($question, $ans, $defaults = array()){
	if(isset($defaults[$question->question])){
		return $defaults[$question->question];
	}

	if(!$ans |! $_POST){
		return '';
	}
	
	if(!isset($_POST['user-answer-' . $question->ID])){
		return '';
	}

	return esc_attr(stripslashes($_POST['user-answer-' . $question->ID]));
}

if(!is_single() and isset($GLOBALS['surveys_client_includes_loaded'])) { #If this is in the listing page - and a quiz is already shown, don't show another.
	printf(t("Please go to <a href='%s'>%s</a> to view the survey"), get_permalink(), get_the_title());
} else {

global $wpdb;

$question = $wpdb->get_results($wpdb->prepare("SELECT ID,question,allow_user_answer,allow_multiple_answers,user_answer_format, required FROM {$wpdb->prefix}surveys_question WHERE survey_ID=%d ORDER BY ID", $survey_id));

$errors = array();


if($_POST){
// Validate the survey

	foreach($question as $q){

		if($q->required){
			
			if(!isset($_POST["answer-{$q->ID}"]) || !$_POST["answer-{$q->ID}"]){
				$errors["answer-{$q->ID}"] = array(
					'type' => 'required'
				);
			} 
			
			// Check a user answer has been entered
			if(is_array($_POST["answer-{$q->ID}"]) && in_array("user-answer", $_POST["answer-{$q->ID}"])){
				
				if(!isset($_POST["user-answer-{$q->ID}"]) || !$_POST["user-answer-{$q->ID}"]){

					$errors["answer-{$q->ID}"] = array(
						'type' => 'required'
					);
				}
			} 
		}
	}
}

if(isset($_POST['action']) && $_POST['action'] && !$errors) { 
	// Save the survey
		//Save the survey details.
		//$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}surveys_result (survey_ID, added_on) VALUES(%d, DATE_ADD(NOW(), INTERVAL %f HOUR))", $survey_id, get_option('gmt_offset')));
		$wpdb->query($wpdb->prepare($sql = "INSERT INTO {$wpdb->prefix}surveys_result (survey_ID, added_on, user_id) VALUES(%d, NOW(), " . wp_get_current_user()->ID . ")", $survey_id));
		$result_id = $wpdb->insert_id;
		
		
		unset($_SESSION['___SURVEYS_COMPLETION___']);
		
		$question_count = 0;
		foreach($_POST['question_id'] as $question_id) {
			if(!$_POST['answer-' . $question_id]) { //User ignored the question.
				$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}surveys_result_answer (result_ID, answer_ID, question_ID) VALUES(%d, %d, %d)", 
					$result_id, 0, $question_id)); // Add an empty answer row.
			
			} else {
				foreach($_POST['answer-' . $question_id] as $answer_id) {
				$user_answer = '';
				
				if($answer_id == 'user-answer') { //Custom answer provided by the user.
					$answer_id = 0;
					$user_answer = $_POST['user-answer-' . $question_id]; //Get the user answer from the text input field.
				
				} elseif(!$answer_id) $answer_id = 0; //Question was ignored.
				
				$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}surveys_result_answer (result_ID, answer_ID, question_ID, user_answer) VALUES(%d, %d, %d, %s)", 
												$result_id, $answer_id, $question_id, strip_tags($user_answer)));
				
				if(!$question[$question_count]->allow_multiple_answers) break; // If this question don't allow multiple answers, break to the next question. This is basically a security measure. Users will have to edit the page HTML to make this necessary(very unlikely.).
				}
			}
			$question_count++;
		}
		
		$email = get_option('surveys_email');
		if($email) {
			
			$email_body = printf(t("Hi,\nThere is a new result for the survey at %s...\n"), $_SERVER['REQUEST_URI']);
			
			//Code lifted from show_individual_response.php file
			$questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}surveys_question WHERE survey_ID=%d", $survey_id));

			foreach($questions as $q) {
				$email_body .= $q->question . "\n";
				$all_answers_for_question = $wpdb->get_results($wpdb->prepare("SELECT A.answer,RA.answer_ID,RA.user_answer 
						FROM {$wpdb->prefix}surveys_result_answer AS RA 
						LEFT JOIN {$wpdb->prefix}surveys_answer AS A 
						ON A.ID=RA.answer_ID WHERE RA.result_ID=%d AND RA.question_ID=%d", $result_id, $q->ID));
				
				$answers = array();
				foreach($all_answers_for_question as $one_answer) { // There is a chance that there is multiple answers for this question.
					if($one_answer->answer_ID) $answers[] = stripslashes($one_answer->answer);
					else $answers[] = stripslashes($one_answer->user_answer); //Custom User answer.
				}
				
				$email_body .= t("Answer: ");
				if($q->allow_user_answer and $q->user_answer_format == 'checkbox') {
					if($answers[0]) $email_body .= 'Yes';
					else $email_body .= 'No';
				} else {
					$email_body .= implode(', ', $answers);
				}
				$email_body .= "\n\n";
			}
			

			mail($email, t("Survey Result"), $email_body);

		}
		
        echo apply_filters('survey_success', 
            "<h2>Survey Complete</h2>
		     <p>Thanks for taking the survey. Your input is very valuable to us</p>"
        );

	
} else { // Show The survey.

	if(!isset($GLOBALS['surveys_client_includes_loaded'])) {
?>
<link type="text/css" rel="stylesheet" href="<?php echo $GLOBALS['wpframe_plugin_folder'] ?>/style.css" />
<script type="text/javascript" src="<?php echo $GLOBALS['wpframe_wordpress'] ?>/wp-includes/js/jquery/jquery.js"></script>
<script type="text/javascript" src="<?php echo $GLOBALS['wpframe_plugin_folder'] ?>/script.js"></script>
<?php
		$GLOBALS['surveys_client_includes_loaded'] = true; // Make sure that this code is not loaded more than once.
	}

if($question) {
$questions_per_page = get_option('surveys_questions_per_page');
if(!is_numeric($questions_per_page)) $questions_per_page = 0;

if($errors){
?>
<div class='fusion-alert alert error alert-danger alert-shadow'>
    <div class="msg">Please complete all of the required questions</div>
</div>

<?php
}
?>

<div class="survey-area <?php if($questions_per_page != 1) echo 'multi-question'; ?>">
<form action="" method="post" class="input-form survey-form" id="survey-<?php echo $survey_id?>">
<h2><?php echo $description; ?></h2>

<?php
$question_count = 1;

foreach ($question as $ques) {
	echo "<div class='question-container survey-question' id='question-$question_count'>";
	?>
	<?php if($ques->user_answer_format !== 'hidden'): ?>
	<div class="title">
		<h3><?php echo "<label class='general-label " . ($ques->required ? 'answer_required' : '') . ' ' . (isset($errors["answer-{$ques->ID}"]) ? "error" : "") . "'>{$ques->question}</label>";?></h3>
	</div>
	<?php endif; ?>
	<?php
	echo "<input type='hidden' name='question_id[]' value='{$ques->ID}' />\n";
	$all_answers = $wpdb->get_results("SELECT ID,answer FROM {$wpdb->prefix}surveys_answer WHERE question_id={$ques->ID} ORDER BY sort_order");
	
	$type = ($ques->allow_multiple_answers) ? 'checkbox' : 'radio'; //If this is a multi answer question, make it a checkbox. Else, it will be a radio.
	if(count($all_answers) == 0 and $ques->allow_user_answer) $type = 'hidden'; //If there are no admin specified answer, and it allows user answer, keep it as selected - user don't have to check anything.
	

	
	foreach ($all_answers as $ans) {
		echo "<input type='$type' " . isAnswerSelected($ques, $ans) . " name='answer-{$ques->ID}[]' id='answer-id-{$ans->ID}' class='answer' value='{$ans->ID}' />\n";
		echo "<label for='answer-id-{$ans->ID}'>" . stripslashes($ans->answer) . "</label><br />\n";
	}
	
	if($ques->allow_user_answer) {
		echo "<input type='$type' " . isAnswerSelected($ques, $ans, 'user') . " name='answer-{$ques->ID}[]' id='user-answer-id-{$ans->ID}' class='answer' value='user-answer' />\n";
		
		if($ques->user_answer_format == 'textarea')
			echo "<textarea name='user-answer-{$ques->ID}' rows='5' cols='30' class='user-answer '>" . getUserAnswerValue($ques, $ans, $default_answers) . "</textarea>";
		elseif($ques->user_answer_format == 'checkbox')
			echo "<input type='checkbox' name='user-answer-{$ques->ID}' class='user-answer' value='1' />";
		elseif($ques->user_answer_format == 'hidden')
			echo "<input type='hidden' name='user-answer-{$ques->ID}' class='user-answer general-input' value='" . getUserAnswerValue($ques, $ans, $default_answers) . "' />";
		else
			echo "<input type='text' name='user-answer-{$ques->ID}' class='user-answer general-input' value='" . getUserAnswerValue($ques, $ans, $default_answers) . "' />";
		
		echo "<br />\n";
	}
	
	echo "</div>\n\n";
	$question_count++;
}

?><br />
<input type="button" id="survey-next-question" value="<?php e("Next") ?> &gt;"  /><br />
<div class="button-c-container">
	<button type="submit" name="action" value="save" id="survey-action-button" class="button large green saveform"><?php e("Submit Survey") ?></button>
</div>
<input type="hidden" name="survey_id" value="<?php echo $survey_id ?>" />
</form>

<script type="text/javascript">survey_questions_per_page = <?php echo $questions_per_page ?>;</script>
</div>

<?php }
}
}
?>