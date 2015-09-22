<?php
/**
 * @version      $Id: Ballotdisplay.php Matthew Murphy <matthew.e.murphy@phila.gov>
 * @package      PVPlugins
 * @copyright    Copyright (C) 2015 City of Philadelphia Elections Commission
 * @license      GNU/GPL V2
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

$mainframe->registerEvent('onPrepareContent', 'plgContentBallotdisplay');

function plgContentBallotdisplay(&$row)
{
    if (is_object($row)) {
        return plgBallotdisplay($row->text);
    }
    return plgBallotdisplay($row);
}

function plgBallotdisplay(&$text)
{
    if (JString::strpos($text, 'Ballotdisplay') === false) {
        return true;
    }

    $text = explode('<script', $text);
    foreach ($text as $i => $str) {
        if ($i == 0) {
            plgBallotdisplayString($text[$i]);
        } else {
            $str_split = explode('</script>', $str);
            foreach ($str_split as $j => $str_split_part) {
                if (($j % 2) == 1) {
                    plgBallotdisplayString($str_split[$i]);
                }
            }
            $text[$i] = implode('</script>', $str_split);
        }
    }
    $text = implode('<script', $text);

    return true;
}

function plgBallotdisplayString(&$text)
{
    if (JString::strpos($text, 'Ballotdisplay') === false) {
        return true;
    }

    $search = "({{Ballotdisplay:.*}})";

    while (preg_match($search, $text, $regs, PREG_OFFSET_CAPTURE)) {

        $temp = explode('=', trim(trim($regs[0][0], '{}'), '{}'));
        $field = explode(':', $temp[0])[1];
        $value = $temp[1];

        if ($content = getBallots($field, $value)) {
            $text = str_replace($regs[0][0], $content, $text);
        }
    }
    return true;
}

function getBallots($field, $value)
{
    $db = &JFactory::getDBO();

    switch ($field) {
        case 'name':
            $query = 'SELECT `b`.* from `#__rt_ballot_upload` `b`, `#__rt_election` `e` WHERE `b`.`eid`=`e`.`id` and name=' . $db->quote($value);
            break;

        case 'id':
            $query = 'SELECT * FROM `#__rt_ballot_upload` WHERE `eid`=' . (int) $value;
            break;
        default:
            return false;
            break;
    }

    $db->setQuery($query);
    $results = $db->loadObjectList();
    if (!sizeof($results)) {
        return false;
    }

    return getContent($results);
}

function getContent(&$results)
{

    $return = "";
    foreach ($results as $result) {
        $sid = $result->sid;
        if (JString::strpos($result->sid, '%') !== false && JString::strpos($result->sid, '^') !== false) {
            $sid = trim(str_replace('^', ' ', explode('%', $result->sid)[1]));
        }
        $return .= '<li><a href="/ballot_paper/' . $result->file_id . '.pdf" target="_blank">District ' . $sid . '</a></li>';
    }
    return "<h4>Download Ballots</h4><ul>" . $return . "</ul>";
}
