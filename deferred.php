<?php 

session_start();

if ( !empty ( $_SESSION['erbj_cache'] ) )
{
	echo $_SESSION['erbj_cache'];
	unset ( $_SESSION['erbj_cache'] );
}

?>