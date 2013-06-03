<?php

namespace Fastre\BudgetBundle\Command;


use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Fastre\BudgetBundle\Entity\Entry;
use Fastre\BudgetBundle\Entity\City;
use Fastre\BudgetBundle\Entity\AbstractNode;
use Fastre\BudgetBundle\Entity\CategoryFonction;
use Fastre\BudgetBundle\Entity\Relations\hasCategory;
use Everyman\Neo4j\Cypher\Query;

/**
 * 
 *
 * @author Julien Fastré <julien arobase fastre point info>
 */
class ImportCommand extends ContainerAwareCommand {
    
    const ARGUMENT_FILE = 'file';
    
    const ARGUMENT_CITY = 'ville';
    
    const ARGUMENT_SLUG = 'slug_de_la_ville';
    
    /**
     *
     * @var \Everyman\Neo4j\Client
     */
    private $c;
    
    
    private $currentCity;

    
    protected function configure()
    {
        $this
            ->setName('budget:import')
            ->setDescription('import budget from csv')
            ->addArgument(self::ARGUMENT_CITY, InputArgument::REQUIRED, 'city concerned')
            ->addArgument(self::ARGUMENT_FILE, InputArgument::REQUIRED, 'csv file to import')
            ->addArgument(self::ARGUMENT_SLUG, InputArgument::REQUIRED, 'Slug de la ville')
                ;
        
    }
    
    protected function execute(InputInterface $input, OutputInterface $output) {
        
        
        $client = $this->getContainer()->get('neo4jclient');
        
        $this->c = $client;
        
        //try to get the city
        //TODO

        $ville = $client->makeNode();

        $ville->setProperty(AbstractNode::KEY_ENTITY, City::getEntityValue())
                  ->setProperty(AbstractNode::KEY_LABEL, $input->getArgument(self::ARGUMENT_CITY))
                  ->setProperty(AbstractNode::KEY_CODE, $input->getArgument(self::ARGUMENT_SLUG))
                ;

        $ville->save();
        
        $this->currentCity = $ville;
        
        $this->createRootFunctionalCategories($ville);
        

        if (($handle = fopen($input->getArgument(self::ARGUMENT_FILE), "r")) !== FALSE) {
            
            $data = fgetcsv($handle, 1000, ","); //pass the first line, heading of the file
            
              $i = 1;
//            for ($i = 0; $i < 200; $i++) {
              while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {  
                $data = fgetcsv($handle, 1000, ",");
                
                $num = count($data);
                echo "$num champs à la ligne $i: \n";
                
                
                $entry = $client->makeNode();
                
                $entry->setProperty(Entry::KEY_ENTITY, Entry::getEntityValue())
                        ->setProperty(Entry::KEY_LABEL, $data[8])
                        ->setProperty(Entry::KEY_AMOUNT, (double) $data[15]);
                
                //add indice
                $econ = str_split($data[3]);
                if ($econ[1] == 0) {
                    $indice = 0;
                } elseif ($econ[1] <= 5 ) {
                    $indice = -1;
                } elseif ($econ[1] > 5) {
                    $indice = 1;
                } else {
                    throw new \Exception('bad economical code');
                }
                
                $entry->setProperty(Entry::KEY_INDEX, $indice);
                
                
                $entry->save();
                
                $catF = $this->getOrCreateCategory($data[2]);
                
                $catF->relateTo($entry, 'own')
                        ->save();
                
                
                
               $i++; 
            }
        
         fclose($handle);
        
        }
    }
    
    
    private function getRootCategories() {
        return array(
            '0' => 'Non imputable',
            '1' => 'Administration générale',
            '3' => 'Ordre et sécurité publique',
            '4' => 'Communications',
            '5' => 'Commerce, industrie',
            '6' => 'Agriculture',
            '7' => 'Enseignement',
            '8' => 'Interventions sociales et santé publique',
            '9' => 'Logements sociaux et aménagement du territoire'
        );
    }
    
    
    private function createRootFunctionalCategories($city) {
        $a = $this->getRootCategories();
        
        foreach($a as $key => $label) {
            $n = $this->c->makeNode(array(
                CategoryFonction::KEY_ENTITY => CategoryFonction::getEntityValue(),
                CategoryFonction::KEY_CODE => (string) $key,
                CategoryFonction::VALUE_TYPE => $key
            ))
                    ->save();
            
            $city->relateTo($n, 'hasCategory')
                    ->save();
        }
        
    }
    
    
    private function getOrCreateCategory($code, $row = 'last') {
        
        if ($code === '000') {
            $code = '0';
        }
        
        $a = str_split($code);
        
        if ($row == 'last') {
            $row = count($a);
        }
        
        $preparedCode = '';
        for ($i = 0; $i < $row; $i++) {
            
            $preparedCode.= $a[$i];
            
        }
        
        
        
        $queryString = 'START n=node('.$this->currentCity->getId().') 
            MATCH n-['.hasCategory::getName().'*1..6]->x 
            WHERE x.entity=\''.CategoryFonction::getEntityValue().'\' AND x.'.CategoryFonction::KEY_CODE.' = \''.$preparedCode.'\'
            RETURN x';
        
        echo 'Prepare Query '.$queryString."\n";
        
        $query = new Query($this->c, $queryString);
        
        $result = $query->getResultSet();
        
        echo $result->count()." Résultats \n";
        
        if ($result->count() === 1 ) {
            return $result[0]['x'];
        } elseif($result->count() > 1) {
            throw new \Exception('Results should not be more than one...');
        } elseif($result->count() === 0) {
            
            $parentCode = '';
            for ($i = 0; $i < $row-1; $i++) {
                $parentCode.= $a[$i];
            }
            
            $parent = $this->getOrCreateCategory($parentCode);
            
            $child = $this->c->makeNode(array(
                CategoryFonction::KEY_ENTITY => CategoryFonction::getEntityValue(),
                CategoryFonction::KEY_CODE => $preparedCode,
                CategoryFonction::KEY_LABEL => '',
            ))
                    ->save();
            
            $parent->relateTo($child, hasCategory::getName())
                    ->save();
            
            return $child;
        }
        
    }
    
    

    
    
    
}

