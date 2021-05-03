<?php

function increment_browser($browser)
{
    try {
        $sqlite = new \SQLite3('/tmp/landingpage.db');
    } catch (Exception $e) {
        return false;
    }
    $sqlite->exec('CREATE TABLE IF NOT EXISTS user_agents  (browser TEXT NOT NULL);');
    $statement = $sqlite->prepare('INSERT INTO user_agents (browser) VALUES(:browser);');
    $statement->bindValue(':browser', $browser, SQLITE3_TEXT);
    try {
        $ret = $statement->execute();
    } catch (Exception $e) {
        return false;
    }
    return $ret;
}

function identifyUserAgent($userAgent)
{
    if (preg_match('/^Mozilla/', $userAgent)) {
        if (preg_match('/Chrome\/[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}/', $userAgent)) {
            increment_browser('chrome');
        } elseif (preg_match('/Safari\/[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}/', $userAgent)) {
            increment_browser('safari');
        } elseif (strpos($userAgent, 'MSIE ') || preg_match('/Trident\/[0-9]/', $userAgent)) {
            increment_browser('internet_explorer');
        } elseif (preg_match('/Firefox\/[0-9]{1,3}\.[0-9]{1,3}/', $userAgent)) {
            increment_browser('firefox');
        } else {
            increment_browser('other');
        }
    } elseif (preg_match('/^Opera\/[0-9]{1,3}\.[0-9]/', $userAgent)) {
        increment_browser('opera');
    } else {
        increment_browser('other');
    }
}

identifyUserAgent($_SERVER['HTTP_USER_AGENT']);

require_once('/etc/pineapple/landingpage.php');
