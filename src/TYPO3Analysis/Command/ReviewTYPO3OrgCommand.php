<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
 */
namespace TYPO3Analysis\Command;

use Gerrie\Helper\Configuration;
use Gerrie\Helper\Factory;
use Gerrie\Gerrie;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use TYPO3Analysis\Helper\Database;
use TYPO3Analysis\Helper\MessageQueue;

class ReviewTYPO3OrgCommand extends Command {

    /**
     * JSON File with all information we need
     *
     * @var string
     */
    const CONFIG_FILE = 'gerrit-review.typo3.org.yml';

    /**
     * Message Queue Queue
     *
     * @var string
     */
    const QUEUE = 'import.gerritproject';

    /**
     * Message Queue routing
     *
     * @var string
     */
    const ROUTING = 'import.gerritproject';

    /**
     * Config
     *
     * @var array
     */
    protected $config = array();

    /**
     * Database connection
     *
     * @var \TYPO3Analysis\Helper\Database
     */
    protected $database = null;

    /**
     * MessageQueue connection
     *
     * @var \TYPO3Analysis\Helper\MessageQueue
     */
    protected $messageQueue = null;

    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure() {
        $this->setName('typo3:review.typo3.org')
             ->setDescription('Queues tasks to import projects of review.typo3.org');
    }

    /**
     * Initializes the command just after the input has been validated.
     *
     * Sets up the config, database and message queue
     *
     * @param InputInterface    $input      An InputInterface instance
     * @param OutputInterface   $output     An OutputInterface instance
     * @return void
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->config = Yaml::parse(CONFIG_FILE);

        $config = $this->config['MySQL'];
        $projectConfig = $this->config['Projects']['TYPO3'];
        $this->database = new Database($config['Host'], $config['Port'], $config['Username'], $config['Password'], $projectConfig['MySQL']['Database']);

        $config = $this->config['RabbitMQ'];
        $this->messageQueue = new MessageQueue($config['Host'], $config['Port'], $config['Username'], $config['Password'], $config['VHost']);
    }

    /**
     * Executes the current command.
     *
     * Reads all versions from get.typo3.org/json, store them into a database
     * and add new messages to message queue to download this versions.
     *
     * @param InputInterface    $input      An InputInterface instance
     * @param OutputInterface   $output     An OutputInterface instance
     * @return null|integer null or 0 if everything went fine, or an error code
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $project = 'TYPO3';

        // Bootstrap Gerrie
        $gerrieConfig = $this->initialGerrieConfig(dirname(CONFIG_FILE));
        $databaseConfig = $gerrieConfig->getConfigurationValue('Database');
        $projectConfig = $gerrieConfig->getConfigurationValue('Gerrit.' . $project);

        $gerrieDatabase = new \Gerrie\Helper\Database($databaseConfig);
        $gerrieDataService = Factory::getDataService($gerrieConfig, $project);

        $gerrie = new Gerrie($gerrieDatabase, $gerrieDataService, $projectConfig);
        $gerrie->proceedServer($project, $gerrieDataService->getHost());

        $projects = $gerrieDataService->getProjects();

        if ($projects === null) {
            return;
        }

        $parentMapping = array();
        foreach($projects as $name => $info) {
            $projectId = $gerrie->importProject($name, $info, &$parentMapping);
            var_dump($projects);
            die();

            // Import / update single project via Gerrie
            // Return the insert id
            // Add a new RabbitMQ message to import single project
        }

        $gerrie->proceedProjectParentChildRelations($parentMapping);

        return null;
    }

    protected function initialGerrieConfig($configDir) {
        $configFile = rtrim($configDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $configFile .= static::CONFIG_FILE;

        $gerrieConfig = new Configuration($configFile);
        return $gerrieConfig;
    }
}