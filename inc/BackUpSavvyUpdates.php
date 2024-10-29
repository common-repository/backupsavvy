<?php

class backupSavvyUpdates
{
  private $slug; // plugin slug
  private $pluginData = array();
  private $place;
  private $pluginFile; // __FILE__ of our plugin
  private $gitlabAPIResult; // holds data from GitLab
  private $tags_path;
  private $archive_path;
  private $readme_path;
  private $access;
  private $source_path;

  function __construct($pluginFile, $response)
  {

    $this->pluginFile = $pluginFile;

    $this->set_data($response);
    $this->activate();
  }

  private function activate()
  {

    if (!$this->access)
      return false;

    if (!function_exists('get_plugin_data')) {
      require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    $plagin_data = get_plugin_data($this->pluginFile);
    $add_data = array(
      'settings-array-key' => plugin_basename($this->pluginFile),
      'slug' => $this->slug,
      'gitlab-url' => untrailingslashit($this->source_path),
      'repo' => $this->place,
      'access-token' => $this->access
    );

    $this->pluginData = array_merge($plagin_data, $add_data);
    $this->set_paths();
    $this->replace_plugin();
  }

  private function replace_plugin() {

    require_once(ABSPATH . 'wp-admin/includes/admin.php');

    // check last version
    $this->setRepoReleaseInfo();
    $latest_version = $this->gitlabAPIResult['name'];
    $plugin_package = $this->archive_path."&sha=$latest_version";

    include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    wp_cache_flush();

    $upgrader = new Plugin_Upgrader();
    $installed = $upgrader->install( $plugin_package );

    $source = 'backupsavvy-premium-'.$this->gitlabAPIResult['name'].'-'.$this->gitlabAPIResult['target'];
    $path = 'backupsavvy';

    $this->filter_source_name($source, $path);
  }

  private function setRepoReleaseInfo()
  {
    if (!empty($this->gitlabAPIResult)) {
      return;
    }


    $tag_info = $this->fetch_tags_from_repo();

    if(!$tag_info)
      return;

    // get the last release
    if (is_array($tag_info))
      $tag_info = (array) $tag_info[0];

    $this->gitlabAPIResult = $tag_info;

    return;
  }

  /**
   * Fetch data of latest version.
   *
   * @return array|WP_Error|false Array with data of the latest version or WP_Error.
   */
  protected function fetch_tags_from_repo()
  {
    $request = wp_safe_remote_get($this->tags_path);

    $response_code = wp_remote_retrieve_response_code($request);

    if (is_wp_error($request) || 200 !== $response_code) {
      return false;
    } else {
      $response = wp_remote_retrieve_body($request);
    }

    $data = json_decode($response);
//      error_log('$data'.print_r($data,1));
    /**
     * Check if we have no tags and return the transient.
     */
    if (empty($data))
      return false;

    return $data;
  }

  /**
   * Renames the source directory and returns new $source.
   *
   * @param string $source URL of the tmp folder with the theme or plugin files.
   * @param string $path Source URL on remote.
   *
   * @return string
   */
  protected function filter_source_name($source, $path)
  {
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    WP_Filesystem();
    global $wp_filesystem;

    $source = ABSPATH.'wp-content/plugins/'.$source;
    $path = ABSPATH.'wp-content/plugins/'.$path;

    /**
     * Check if the remote source directory exists.
     */
    if ($wp_filesystem->exists($source)) {
      /**
       * Copy files from $source in new $upgrade_theme_folder
       */
      copy_dir($source, $path);

      /**
       * Remove the old $source directory.
       */
      $wp_filesystem->delete($source, true);

      /**
       * Set new folder as $source.
       */
      $source = $path;
    }

    return $source;
  }

  private function set_data($response) {

    if (!$response)
      return false;

    $this->source_path = $response->source_path;
    $this->access = $response->access;
    $this->place = $response->place;
    $this->slug = dirname($this->pluginFile);
    $this->place = str_replace('/', '%2F', $this->place);
  }

  private function set_paths() {


    $this->tags_path = $this->source_path."/api/v4/projects/$this->place/repository/tags?private_token=".$this->access;
    $this->archive_path = $this->source_path . "/api/v4/projects/$this->place/repository/archive.zip?private_token=".$this->access;
    $this->readme_path = $this->source_path . "/api/v4/projects/$this->place/repository/files/readme.txt?ref=master&private_token=".$this->access;

  }




}