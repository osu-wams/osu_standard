<?php

namespace Drupal\osu_standard\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\cas\Service\CasUserManager;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class OsuDrushCommands
 *
 * Provides custom Drush commands for handling aliases in a Drupal site.
 */
class OsuDrushCommands extends DrushCommands
{
  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
    protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The batch size to use.
   *
   * @var int
   */
    private int $batchSize = 50;

  /**
   * @var \Drupal\cas\Service\CasUserManager
   */
    private CasUserManager $casUserManager;

  /**
   * @var \Drupal\Core\Datetime\DateFormatter
   */
    private DateFormatter $dateFormatter;

  /**
   * Construct an OSU Commands object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   */
    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        CasUserManager $casUserManager,
        DateFormatter $dateFormatter
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->casUserManager = $casUserManager;
        $this->dateFormatter = $dateFormatter;
    }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *
   * @return static
   */
    public static function create(ContainerInterface $container): static
    {
        return new static(
            $container->get('entity_type.manager'),
            $container->get('cas.user_manager'),
            $container->get('date.formatter')
        );
    }

  /**
   * Set the "generate aliases automatically" setting for nodes.
   */
    #[CLI\Command(name: 'osu:set-generate-alias', aliases: ['osugalias'])]
    #[CLI\Argument('entity_type', description: 'The entity type (e.g. node).')]
    #[CLI\Argument('bundle', description: 'The bundle to filter by.')]
    #[CLI\Argument('ids', description: "A CSV string of entity ID's to update.")]
    public function setGenerateAlias(string $entity_type, string $bundle, string $ids): void
    {
        $this->updateGenerateAlias($entity_type, $bundle, $ids, true);
    }

  /**
   * Updates the 'Generate automatic URL alias' setting for specified entities.
   *
   * @param string $entity_type
   *   The type of the entity (e.g., 'node', 'user', 'taxonomy_term').
   * @param bool $generate_alias
   *   Whether to enable or disable automatic alias generation for the entities.
   * @param string $bundle
   *   (Optional) The entity bundle type to filter by, if applicable.
   * @param string $ids
   *   (Optional) A comma-separated list of entity IDs to process. If null, all
   *   entities of the specified type and bundle will be processed.
   *
   * @return void
   */
    private function updateGenerateAlias(
        string $entity_type,
        bool $generate_alias,
        string $bundle,
        string $ids
    ): void {
        try {
            $storage = $this->entityTypeManager->getStorage($entity_type);
        } catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
            $this->logger()->error('Failed to load @type storage: @message', [
            '@type' => $entity_type,
            '@message' => $e->getMessage(),
            ]);
            $this->output()->writeln('Operation aborted due to storage error.');
            return;
        }

        $query = $storage->getQuery();
      // No access checks needed.
        $query->accessCheck(false);

        if ($bundle) {
            $query->condition('type', $bundle);
        }
        if ($ids) {
            $id_array = array_map('trim', explode(',', $ids));
            switch ($entity_type) {
                case 'node':
                    $query->condition('nid', $id_array, 'IN');
                    break;
                case 'user':
                    $query->condition('uid', $id_array, 'IN');
                    break;
                case 'taxonomy_term':
                    $query->condition('tid', $id_array, 'IN');
                    break;
                default:
                    $query->condition('id', $id_array, 'IN');
                    break;
            }
        }
        $query->range(0, $this->batchSize);
        $total = 0;
        $errors = 0;
        while ($entitie_ids = $query->execute()) {
            $entities = $storage->loadMultiple($entitie_ids);

            foreach ($entities as $entity) {
                try {
                    $path = $entity->get('path');
                    $path->pathauto = $generate_alias;
                    $entity->save();
                    $total++;
                } catch (EntityStorageException $entityStorageException) {
                    $errors++;
                    $entity_id = $entity->id();
                    $this->logger()
                    ->error('Failed to update @type entity (ID: @id): @message', [
                    '@type' => $entity_type,
                    '@id' => $entity_id,
                    '@message' => $entityStorageException->getMessage(),
                    ]);
                }
            }
            $this->output->writeln('Processed ' . $total . ' ' . $entity_type . '.');

          // Reset the query for the next batch.
            $query->range($total, $this->batchSize);
        }
        $summary = "Total $entity_type processed: $total";
        if ($errors > 0) {
            $summary .= " (with $errors errors, see logs for details)";
        }
        $summary .= ". Generate aliases automatically set to " . ($generate_alias ? 'true' : 'false') . '.';

        $this->output()->writeln($summary);
    }

  /**
   * Set the "generate aliases automatically" setting for nodes.
   */
    #[CLI\Command(name: 'osu:unset-generate-alias', aliases: ['osuusgalias'])]
    #[CLI\Argument('entity_type', description: 'The entity type (e.g. node).')]
    #[CLI\Argument('bundle', description: 'The bundle to filter by.')]
    #[CLI\Argument('ids', description: "A CSV string of entity ID's to update.")]
    public function unsetGenerateAlias(string $entity_type, string $bundle, string $ids): void
    {
        $this->updateGenerateAlias($entity_type, $bundle, $ids, false);
    }

  /**
   * Generate a report of users.
   *
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
    #[CLI\Command(name: 'osu:user-report')]
    #[CLI\Help('Generate a report of users.')]
    #[CLI\FieldLabels(labels: [
    'uid' => 'ID',
    'name' => 'Username',
    'cas' => 'CAS',
    'mail' => 'Email',
    'status' => 'Status',
    'init' => 'Initial Mail',
    'created' => 'Created',
    'changed' => 'Updated',
    'access' => 'Last Access',
    'login' => 'Last Login',
    'node_count' => 'Node Count',
    'roles' => 'Roles',
    ])]
    public function userSiteReport($options = ['format' => 'table']): RowsOfFields
    {
        $rows = [];

        $users = $this->entityTypeManager->getStorage('user')->loadMultiple();
        $node_storage = $this->entityTypeManager->getStorage('node');
        foreach ($users as $user) {
            if ($user->id() == 0) {
                continue;
            }
            $user_node_count = $node_storage->loadByProperties(['uid' => $user->id()]);
            $rows[$user->id()] = [
            'uid' => $user->id(),
            'name' => $user->get('name')->value,
            'cas' => $this->casUserManager->getCasUsernameForAccount($user->id()),
            'mail' => $user->get('mail')->value,
            'status' => $user->get('status')->value,
            'init' => $user->get('init')->value,
            'created' => $user->get('created')->value,
            'changed' => $user->get('changed')->value,
            'access' => $user->get('access')->value,
            'login' => $user->get('login')->value,
            'node_count' => count($user_node_count),
            'roles' => implode(', ', $user->getRoles()),
            ];
        }
        return new RowsOfFields($rows);
    }
}
