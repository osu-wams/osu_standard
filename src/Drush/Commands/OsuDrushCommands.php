<?php

namespace Drupal\osu_standard\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\cas\Service\CasUserManager;
use Drupal\Core\Datetime\DateFormatter;
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
   * @param string|null $bundle
   *   (Optional) The entity bundle type to filter by, if applicable.
   * @param string|null $ids
   *   (Optional) A comma-separated list of entity IDs to process. If null, all
   *   entities of the specified type and bundle will be processed.
   * @param bool $generate_alias
   *   Whether to enable or disable automatic alias generation for the entities.
   *
   * @return void
   */
    private function updateGenerateAlias(
        string $entity_type,
        string $bundle = null,
        string $ids = null,
        bool $generate_alias
    ): void {
        $storage = $this->entityTypeManager->getStorage($entity_type);
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
        while ($entitie_ids = $query->execute()) {
            $entities = $storage->loadMultiple($entitie_ids);

            foreach ($entities as $entity) {
                $path = $entity->get('path');
                $path->pathauto = $generate_alias;
                $entity->save();
                $total++;
            }
            $this->output->writeln('Processed ' . $total . ' ' . $entity_type . '.');

          // Reset the query for the next batch.
            $query->range($total, $this->batchSize);
        }
        $this->output()
        ->writeln('Total ' . $entity_type . ' processed: ' . $total .
        '. Generate aliases automatically set to ' .
        ($generate_alias ? 'true' : 'false') . '.');
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
   */
    #[CLI\Command(name: 'osu:user-report')]
    #[CLI\Help('Generate a report of users.')]
    #[CLI\FieldLabels(labels: [
    'uid' => 'ID',
    'name' => 'User Name',
    'cas' => 'CAS',
    'mail' => 'Email',
    'status' => 'Status',
    'init' => 'Initial Mail',
    'created' => 'Created',
    'changed' => 'Updated',
    'access' => 'Last Access',
    'login' => 'Last Logged In',
    'node_count' => 'Total Authored Nodes',
    'roles' => 'Roles',
    ])]
    public function userSiteReport($options = ['format' => 'yaml']): RowsOfFields
    {
        $rows = [];
        $users = $this->entityTypeManager->getStorage('user')->loadMultiple();
        $node_storage = $this->entityTypeManager->getStorage('node');
        foreach ($users as $user) {
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
