<?php

namespace Apperturedev\CouchbaseBundle\Command;

use Apperturedev\CouchbaseBundle\Classes\CouchbaseORM;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CouchbaseGenerateViewsCommand extends Command
{
    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var CouchbaseORM
     */
    protected $couchbase;

    /**
     * CouchbaseGenerateViewsCommand constructor.
     *
     * @param Registry     $registry
     * @param CouchbaseORM $couchbase
     */
    public function __construct(Registry $registry, CouchbaseORM $couchbase)
    {
        $this->registry  = $registry;
        $this->couchbase = $couchbase;
    }

    protected function configure()
    {
        $this
            ->setName('couchbase:generate:views')
            ->setDescription(
                'Generate views for the entity. To set a Custom View, create a Method getCustomView in your entity'
            )
            ->addArgument('class', InputArgument::OPTIONAL, 'Fully qualified class name of the entity')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $argument   = $input->getArgument('class');
        $em         = $this->registry->getEntityManager();
        $class_name = $em->getClassMetadata($argument)->getName();
        $values     = $em->getClassMetadata($argument)->getFieldNames();
        $table      = $em->getClassMetadata($argument)->getTableName();
        //$output->writeln($values);
        $cb_manager = $this->couchbase->getEm()->manager();
        $output->writeln('Class ' . $class_name);
        $class       = new $class_name;
        $customViews = [];

        if (method_exists($class, 'getCustomView')) {
            $customViews = $class->getCustomView();
        } else {
            $output->writeln('For set Custom View create a Method call getCustomView');
        }
        $view = ['views' => []];
        foreach ($values as $value) {
            if (!isset($customViews['views']) || !in_array($value, $customViews['exclude'], true)) {
                $view['views'][$value] = [
                    'map' => "function (doc, meta) {\n\tif(doc.$table){\n\t\temit(doc.$table.$value, doc.$table);\n\t}\n}"
                ];
                $all[]                 = "doc.$table.$value";
            }
        }
        if (!isset($customViews['views']) || !in_array('all', $customViews['exclude'], true)) {
            $view['views'][$table . '_all'] = [
                'map' => "function (doc, meta) {\n\tif(doc.$table){\n\t\temit([" . implode(
                        ',',
                        $all
                    ) . "], doc.$table);\n\t}\n}"
            ];
        }

        if (isset($customViews['views'])) {
            foreach ($customViews['views'] as $key => $custom) {
                $view['views'][$key] = ['map' => $custom];
            }
        }
        $cb_manager->upsertDesignDocument($table, $view);

        $output->writeln('Views Saved');
    }
}
