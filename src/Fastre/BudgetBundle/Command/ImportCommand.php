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

/**
 * 
 *
 * @author Julien Fastré <julien arobase fastre point info>
 */
class ImportCommand extends ContainerAwareCommand {
    
    const ARGUMENT_FILE = 'file';
    
    const ARGUMENT_CITY = 'ville';
    
    const ARGUMENT_SLUG = 'slug_de_la_ville';
    
    private $c;

    
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

        $ville = $client->makeNode();

        $ville->setProperty(AbstractNode::KEY_ENTITY, City::getEntityValue())
                  ->setProperty(AbstractNode::KEY_LABEL, $input->getArgument(self::ARGUMENT_CITY))
                  ->setProperty(AbstractNode::KEY_CODE, $input->getArgument(self::ARGUMENT_SLUG))
                ;

        $ville->save();
        
        $row = 1;
        if (($handle = fopen($input->getArgument(self::ARGUMENT_FILE), "r")) !== FALSE) {
            
            for ($i = 0; $i < 30; $i++) {
                
                $data = fgetcsv($handle, 1000, ",");
                
                $num = count($data);
                echo "$num champs à la ligne $i: \n";
                
                
                $entry = $client->makeNode();
                
                $entry->setProperty(Entry::KEY_TYPE, Entry::VALUE_TYPE)
                        ->setProperty(Entry::KEY_LABEL, $data[8]);

                $entry->relateTo($ville, 'has');
                
                $entry->save();
                
                
                
            }
            
            
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                
            }
        
         fclose($handle);
        
        }
    }
    
    
    private function getRootCategories() {
        return array(
            '00' => 'Non imputable',
            '01' => 'Administration générale',
            '02' => 'Défense',
            '03' => 'Ordre et sécurité publique',
            '04' => 'Affaires et services économiques',
            '05' => 'Protection de l\'environnement',
            '06' => 'Politique de logements et équipements collectifs',
            '07' => 'Santé',
            '08' => 'Loisirs, culture et cultes',
            '09' => 'Enseignement',
            '10' => 'Protection Sociale'
        );
    }
    
}

