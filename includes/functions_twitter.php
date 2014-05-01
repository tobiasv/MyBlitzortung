<?php

/*
 *	Twitter for myblizortung
 *	inspired by Clément Delande
*/	


function bo_twitter_send_direct_msg($message, $recipient)
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


?>