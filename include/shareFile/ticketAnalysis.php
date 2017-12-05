<?php
namespace shareFile;
require_once('prepend.php');

if(!defined('INCLUDE_DIR')) die('!');
require_once(INCLUDE_DIR.'class.draft.php');
require_once(INCLUDE_DIR.'class.ticket.php');
require_once(SF_SETTINGS_PATH);

/**
 * Returns whether a given entry contains an SFLink or not
 * \param $entry        Entry to examine for an sfLink
 * \return              Boolean, true if contains sflink, false if not
 */
function EntryContainsSFLink($entry){
    //getBody should return a HtmlThreadEntryBody
    $entryBody = $entry->getBody()->getClean();
    $needle = "sharefile.com";
    $stringPosition = strpos ($entryBody , $needle);

    return !($stringPosition === FALSE);
}

/**
 * Will return all entries containing an sfLink
 * \param $entryArray   Array containing ticket entries
 * \return              Array containing ticket entries with sfLinks
 */
function GetSFLinkPosts($entryArray){
    $returnArray = array();

    foreach ($entryArray as $entry){
        if(EntryContainsSFLink($entry)){
            array_push($returnArray, $entry);
        }
    }

    return $returnArray;
}

/**
 * Gets an array of entries from a ticket sorted by date created
 * oldest at the front of the Array
 * newest at the back
 */
function GetEntries($ticket){
    return $ticket->getThread()->getEntries();
}

///Returns last element (newest) of an entries array
function GetLastEntry($entries){
    return ( array_values(array_slice($entries, -1))[0] );
}
