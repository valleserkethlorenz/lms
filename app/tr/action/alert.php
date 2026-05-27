<?php

function success($url, $message)
{
    $_SESSION['message'] = $message;
    header('Location:'.$url);
    exit();
}
function error($url, $message)
{
    $_SESSION['error'] = $message;
    header('Location:'.$url);
    exit();
}
?>