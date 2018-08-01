<?php

$twitteroauth = BO_DIR.'includes/twitteroauth/autoload.php';
	
if (!file_exists($twitteroauth))
	return false;

require $twitteroauth;

use Abraham\TwitterOAuth\TwitterOAuth;

/*
 *	Twitter for myblizortung
 *	inspired by Clément Delande
*/	


function bo_twitter_send_direct_msg_old($message, $recipient)
{
	if (BO_TWITTER_ENABLED !== true)
		return null;
		
	$codebird = BO_DIR.'includes/codebird/src/codebird.php';
		
	if (!file_exists($codebird))
		return false;
		

	require_once($codebird);

	\Codebird\Codebird::setConsumerKey(BO_TWITTER_API_KEY, BO_TWITTER_API_SECRET);
	$cb = \Codebird\Codebird::getInstance();
	$cb->setToken(BO_TWITTER_ACCESS_TOKEN, BO_TWITTER_ACCESS_SECRET);

	$params = array(
		'text' => $message,
		'screen_name' => $recipient
	);

	$reply = $cb->directMessages_new($params);

	return true;
}


function bo_twitter_send_direct_msg($message, $recipient)
{
	if (BO_TWITTER_ENABLED !== true)
		return null;

	$connection = new TwitterOAuth(BO_TWITTER_API_KEY, BO_TWITTER_API_SECRET, BO_TWITTER_ACCESS_TOKEN, BO_TWITTER_ACCESS_SECRET);

	$result = $connection->get('users/show', array('screen_name' => $recipient));
	$user_id = $result->id;
	
	
	$options = array('event' => 
		array('type' => 'message_create',
			'message_create' => array(
				'target' 		=> array('recipient_id' => $user_id), 
				'message_data' 	=> array('text' 		=> $message)
			)
		)
	);
	
	$result = $connection->post('direct_messages/events/new', $options, true);

	return true;
}

?>