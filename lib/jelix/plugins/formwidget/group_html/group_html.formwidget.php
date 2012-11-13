<?php
/**
* @package     jelix
* @subpackage  formwidgets
* @author      Claudio Bernardes
* @contributor Laurent Jouanneau, Julien Issler, Dominique Papin
* @copyright   2012 Claudio Bernardes
* @copyright   2006-2012 Laurent Jouanneau, 2008-2011 Julien Issler, 2008 Dominique Papin
* @link        http://www.jelix.org
* @licence     http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public Licence, see LICENCE file
*/

/**
 * HTML form builder
 * @package     jelix
 * @subpackage  jelix-plugins
 * @link http://developer.jelix.org/wiki/rfc/jforms-controls-plugins
 */

class group_htmlFormWidget extends \jelix\forms\HtmlWidget\WidgetBase {
    function outputJs() { /* no js */ }
    
    function outputControl() {
        $attr = $this->getControlAttributes();

        echo '<fieldset id="',$attr['id'],'"><legend>',htmlspecialchars($this->ctrl->label),"</legend>\n";
        echo '<table class="jforms-table-group" border="0">',"\n";
        foreach( $this->ctrl->getChildControls() as $ctrlref=>$c){
            if($c->type == 'submit' || $c->type == 'reset' || $c->type == 'hidden') continue;
            if(!$this->builder->getForm()->isActivated($ctrlref)) continue;
            echo '<tr><th scope="row">';
            $this->builder->outputControlLabel($c);
            echo "</th>\n<td>";
            $this->builder->outputControl($c);
            echo "</td></tr>\n";
        }
        echo "</table></fieldset>";
    }
}
