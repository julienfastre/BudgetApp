<?php

namespace Fastre\BudgetBundle\Entity;

/**
 * Description of CategoryFonction
 *
 * @author Julien Fastré <julien arobase fastre point info>
 */
class RootCategoryFonction  extends AbstractNode {
    
   const VALUE_TYPE = 'rootfcode';
   
   
    
    
   public static function getEntityValue() {
        return self::VALUE_TYPE;
    }    
}

