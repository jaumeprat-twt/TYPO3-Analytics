<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
 */
namespace TYPO3Analysis\Consumer\Crawler;

use TYPO3Analysis\Consumer\ConsumerAbstract;

class GerritProject extends ConsumerAbstract {

    /**
     * Gets a description of the consumer
     *
     * @return string
     */
    public function getDescription() {
        return 'Imports a single project of a Gerrit review system';
    }

    /**
     * Initialize the consumer.
     * Sets the queue and routing key
     *
     * @return void
     */
    public function initialize() {
        $this->setQueue('crawler.gerritproject');
        $this->setRouting('crawler.gerritproject');
    }

    /**
     * The logic of the consumer
     *
     * @param \stdClass $message
     * @return null|void
     */
    public function process($message) {
        $this->setMessage($message);
        $messageData = json_decode($message->body);

        $this->getLogger()->info('Receiving message', (array) $messageData);

        if (file_exists($messageData->configFile) === false) {
            $context = array('file' => $messageData->configFile);
            $this->getLogger()->critical('Gerrit config file does not exist', $context);
            $this->acknowledgeMessage($message);
            return;
        }

        $project = $messageData->project;

        // Bootstrap Gerrie
        $gerrieConfig = $this->initialGerrieConfig($messageData->configFile);
        $databaseConfig = $gerrieConfig->getConfigurationValue('Database');
        $projectConfig = $gerrieConfig->getConfigurationValue('Gerrit.' . $project);

        $gerrieDatabase = new \Gerrie\Helper\Database($databaseConfig);
        $gerrieDataService = \Gerrie\Helper\Factory::getDataService($gerrieConfig, $project);

        $gerrie = new \Gerrie\Gerrie($gerrieDatabase, $gerrieDataService, $projectConfig);
        $gerrie->setOutput($this->getLogger());

        $gerritHost = $gerrieDataService->getHost();
        $gerritProject = $gerrie->getGerritProjectById($messageData->serverId, $messageData->projectId);

        $context = array(
            'serverId' => $messageData->serverId,
            'projectId' => $messageData->projectId
        );
        if ($gerritProject === false) {
            $this->getLogger()->critical('Gerrit project does not exists in database', $context);
            $this->acknowledgeMessage($message);
            return;
        }

        $this->getLogger()->info('Start importing of changesets for Gerrit project', $context);

        $gerrie->proceedChangesetsOfProject($gerritHost, $gerritProject);

        $this->getLogger()->info('Import of changesets for Gerrit project successful', $context);

        $this->acknowledgeMessage($message);

        $this->getLogger()->info('Finish processing message', (array) $messageData);
    }

    /**
     * Initialize the Gerrit configuration
     *
     * @param string    $configFile
     * @return \Gerrie\Helper\Configuration
     */
    protected function initialGerrieConfig($configFile) {
        $gerrieConfig = new \Gerrie\Helper\Configuration($configFile);
        return $gerrieConfig;
    }
}