<?php

namespace Drupal\example_module\Plugin\migrate\source;

use Drupal\migrate_source_csv\Plugin\migrate\source\CSV as SourceCSV;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\MigrateException;
use Drupal\Core\Site\Settings;
use phpseclib\Net\SFTP;


require_once DRUPAL_ROOT . '/core/includes/file.inc';

/**
 * Source for download CSV files.
 *
 * @MigrateSource(
 *   id = "migrate_example_source_csv"
 * )
 */
class MigrateExampleSourceRemoteCSV extends SourceCSV {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    // Prepare connection parameters.
    if (!empty($configuration['ftp'])) {
      if (!isset($configuration['ftp']['settings'])) {
        throw new MigrateException('Please set parameter "settings" for source plugin "example_remote_csv".');
      }
      // Merge global settings.
      $conn_config = static::getFTPConfig($configuration['ftp']['settings']);
      $configuration['ftp'] += $conn_config;
      $configuration['path'] = $this->downloadFile($configuration['ftp']);
    }
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
  }

  /**
   * Returns SFTP connection configuration from $settings[sftp].
   *
   * @param string $key
   *   The settings key.
   *
   * @return array
   *   Connection configuration.
   */
  public static function getFTPConfig($key) {
    $conn_config = Settings::get('sftp', []);
    if (!isset($conn_config[$key])) {
      throw new MigrateException("FTP configuration must be set in \$settings[sftp][$key].");
    }
    return $conn_config[$key];
  }

  /**
   * Returns an SFTP connection with the given configuration.
   *
   * @param array $conn_config
   *   Connection parameters.
   *
   * @return SFTP
   *   The SFTP Connection.
   */
  public static function getSFTPConnection(array $conn_config) {
    static $connections;
    $key = $conn_config['server'];
    if (!is_array($connections) || !isset($connections[$key])) {
      // Merge with defaults.
      $conn_config = $conn_config + [
        'port' => 22,
      ];
      // Required config parameters must be set.
      foreach (['server', 'username', 'password', 'port'] as $param) {
        if (!isset($conn_config[$param])) {
          throw new MigrateException('Required SFTP parameter ' . $param . ' not defined.');
        }
      }
      // Establish SFTP connection.
      $sftp = new SFTP($conn_config['server'], $conn_config['port']);
      if (TRUE !== $sftp->login($conn_config['username'], $conn_config['password'])) {
        throw new MigrateException('Cannot connect to SFTP server with the given credentials.');
      }
      $connections[$key] = $sftp;
    }
    return $connections[$key];
  }

  /**
   * Downloads a file from SFTP Server.
   *
   * @param array $conn_config
   *   Connection configuration.
   *
   * @throws MigrateException
   *   If something goes wrong.
   *
   * @return string
   *   Path to local cached version of the remote file.
   */
  protected function downloadFile(array $conn_config) {
    // Merge with configuration defaults.
    $conn_config = $conn_config + [
      'port' => 21,
    ];

    // Remote file path must be specified!
    if (empty($conn_config['path'])) {
      throw new MigrateException('Required parameter "path" not defined.');
    }

    // Prepare file metadata.
    $path_remote = $conn_config['path'];
    $basename = basename($path_remote);
    $path_local = file_directory_temp() . '/' . $basename;

    // Download file by SFTP...
    $sftp = static::getSFTPConnection($conn_config);
    if (!$sftp->get($path_remote, $path_local)) {
      throw new MigrateException('Cannot download remote file ' . $basename . ' by SFTP.');
    }
    // Return path to local (cached version) of the file.
    return $path_local;
  }

}

