<?php

namespace Fastre\BudgetBundle\Entity;

/**
 * Description of Budget
 *
 * @author julienfastre
 */
class Budget extends AbstractNode {
    
    const KEY_YEAR = 'year';
    
    

    public static function getEntityValue() {
        return 'budget';
    }    
}

