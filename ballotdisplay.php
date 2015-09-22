<?php
/**
 * @version     $Id: ballotdisplay.php 
 * @package     PVotes
 * @subpackage  Content
 * @copyright   Copyright (C) 2015 Philadelphia Elections Commission
 * @license     GNU/GPL, see LICENSE.php
 * @author      Matthew Murphy <matthew.e.murphy@phila.gov>
 */

// Check to ensure this file is included in Joomla!
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport( 'joomla.plugin.plugin' );
jimport( 'kint.kint' );
ini_set('display_errors', 1);
/**
 * Example Content Plugin
 *
 * @package     Joomla
 * @subpackage  Content
 * @since       1.5
 */
class plgContentBallotdisplay extends JPlugin
{

    /**
     * Constructor
     *
     * For php4 compatability we must not use the __constructor as a constructor for plugins
     * because func_get_args ( void ) returns a copy of all passed arguments NOT references.
     * This causes problems with cross-referencing necessary for the observer design pattern.
     *
     * @param object $subject The object to observe
     * @param object $params  The object that holds the plugin parameters
     * @since 1.5
     */
    function plgContentBallotdisplay( &$subject, $params )
    {
        parent::__construct( $subject, $params );
    }

    /**
     * Example prepare content method
     *
     * Method is called by the view
     *
     * @param   object      The article object.  Note $article->text is also available
     * @param   object      The article params
     * @param   int         The 'page' number
     */
    function onPrepareContent( &$article, &$params, $limitstart )
    {
        global $mainframe;
        if (is_object($article)) {
            return $this->prepBallotDisplay($article->text);
        }
        return $this->prepBallotDisplay($article);
    }

    /**
     * Example after display title method
     *
     * Method is called by the view and the results are imploded and displayed in a placeholder
     *
     * @param   object      The article object.  Note $article->text is also available
     * @param   object      The article params
     * @param   int         The 'page' number
     * @return  string
     */
    function onAfterDisplayTitle( &$article, &$params, $limitstart )
    {
        global $mainframe;

        return '';
    }

    /**
     * Example before display content method
     *
     * Method is called by the view and the results are imploded and displayed in a placeholder
     *
     * @param   object      The article object.  Note $article->text is also available
     * @param   object      The article params
     * @param   int         The 'page' number
     * @return  string
     */
    function onBeforeDisplayContent( &$article, &$params, $limitstart )
    {
        global $mainframe;

        return '';
    }

    /**
     * Example after display content method
     *
     * Method is called by the view and the results are imploded and displayed in a placeholder
     *
     * @param   object      The article object.  Note $article->text is also available
     * @param   object      The article params
     * @param   int         The 'page' number
     * @return  string
     */
    function onAfterDisplayContent( &$article, &$params, $limitstart )
    {
        global $mainframe;

        return '';
    }

    /**
     * Example before save content method
     *
     * Method is called right before content is saved into the database.
     * Article object is passed by reference, so any changes will be saved!
     * NOTE:  Returning false will abort the save with an error.
     *  You can set the error by calling $article->setError($message)
     *
     * @param   object      A JTableContent object
     * @param   bool        If the content is just about to be created
     * @return  bool        If false, abort the save
     */
    function onBeforeContentSave( &$article, $isNew )
    {
        global $mainframe;

        return true;
    }

    /**
     * Example after save content method
     * Article is passed by reference, but after the save, so no changes will be saved.
     * Method is called right after the content is saved
     *
     *
     * @param   object      A JTableContent object
     * @param   bool        If the content is just about to be created
     * @return  void
     */
    function onAfterContentSave( &$article, $isNew )
    {
        global $mainframe;

        return true;
    }

    function prepBallotDisplay(&$text)
    {
        // Quick, cheap chance to back out.
        if (JString::strpos($text, 'Ballotdisplay') === false) {
            return true;
        }

        $text = explode('<script', $text);
        foreach ($text as $i => $str) {
            if ($i == 0) {
                $this->getBallotdisplayStrings($text[$i]);
            } else {
                $str_split = explode('</script>', $str);
                foreach ($str_split as $j => $str_split_part) {
                    if (($j % 2) == 1) {
                        $this->getBallotdisplayStrings($str_split[$i]);
                    }
                }
                $text[$i] = implode('</script>', $str_split);
            }
        }
        $text = implode('<script', $text);

        return true;
    }

    function getBallotdisplayStrings(&$text)
    {
        // Quick, cheap chance to back out.
        if (JString::strpos($text, 'Ballotdisplay') === false) {
            return true;
        }

        $search = "(\[\[Ballotdisplay:.*\]\])";

        while (preg_match($search, $text, $regs, PREG_OFFSET_CAPTURE)) {

            $temp = explode('=', trim(trim($regs[0][0], '[]'), '[]'));
            if (sizeof($temp) === 2) {
                $temp2 = explode(':', $temp[0]);
                $field = $temp2[1];
                $value = $temp[1];
            }

            if ($field && $value && $content = $this->getBallots($field, $value)) {
                $text = JString::str_ireplace($regs[0][0], $content, $text);
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
        try {
            $results = $db->loadObjectList();
        } catch (Exception $e) {
            return "Oops.  Something has goine wrong while looking for <b>$field</b> : <b>$value</b>.";
        }
        if (!sizeof($results)) {
            return "There are no Sample Ballots available for <b>$field</b> : <b>$value</b>.";;
        }
        return $this->getContent($results);
    }

    function getContent(&$results)
    {
        $return = "";
        foreach ($results as $result) {
            $sid = $result->sid;
            if (JString::strpos($result->sid, '%') !== false && JString::strpos($result->sid, '^') !== false) {
                $sid = JString::trim(JString::str_ireplace('^', ' ', explode('%', $result->sid)[1]));
            }
            $return .= '<li><a href="/ballot_paper/' . $result->file_id . '.pdf" target="_blank">District ' . $sid . '</a></li>';
        }
        return "<h4>Download Sample Ballots</h4><ul>" . $return . "</ul>";
    }
}
