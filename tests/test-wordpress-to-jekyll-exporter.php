<?php

class WordPressToJekyllExporterTest extends WP_UnitTestCase {

  function setUp() {
    parent::setUp();
    $author = wp_insert_user(array(
      "user_login"   => "testuser",
      "user_pass"    => "testing",
      "display_name" => "Tester",
    ));

    $category_id = wp_insert_category(array('cat_name' => 'Testing'));

    wp_insert_post(array(
      "post_name"     => "test-post",
      "post_title"    => "Test Post",
      "post_content"  => "This is a test <strong>post</strong>.",
      "post_status"   => "publish",
      "post_author"   => $author,
      "post_category" => array($category_id),
      "tags_input"    => array("tag1", "tag2"),
      "post_date"     => "2014-01-01",
    ));

    wp_insert_post(array(
      "post_name"    => "test-page",
      "post_title"   => "Test Page",
      "post_content" => "This is a test <strong>page</strong>.",
      "post_status"  => "publish",
      "post_type"    => "page",
      "post_author"  => $author,
    ));

  }

  function test_activated() {
    global $jekyll_export;
    $this->assertTrue( class_exists( 'Jekyll_Export' ), 'Jekyll_Export class not defined' );
    $this->assertTrue( isset($jekyll_export) );
  }

  function test_loads_dependencies() {
    $this->assertTrue( class_exists( 'Spyc' ), 'Spyc class not defined' );
    $this->assertTrue( class_exists( 'Markdownify\Parser' ), 'Markdownify class not defined' );
  }

  function test_gets_post_ids() {
    global $jekyll_export;
    $this->assertEquals(2, count($jekyll_export->get_posts()));
  }

  function test_convert_meta() {
    global $jekyll_export;
    $posts = $jekyll_export->get_posts();
    $post = get_post($posts[1]);
    $meta = $jekyll_export->convert_meta($post);
    $expected = Array (
      'title'     => 'Test Post',
      'author'    => 'Tester',
      'excerpt'   => '',
      'layout'    => 'post',
      'permalink' => '/?p=9',
    );
    $this->assertEquals($expected, $meta);
  }

  function test_convert_terms() {
    global $jekyll_export;
    $posts = $jekyll_export->get_posts();
    $post = get_post($posts[1]);
    $terms = $jekyll_export->convert_terms($post->ID);
    $this->assertEquals(array(0 => "Testing"), $terms["categories"]);
    $this->assertEquals(array(0 => "tag1", 1 => "tag2"), $terms["tags"]);
  }

  function test_convert_content() {
    global $jekyll_export;
    $posts = $jekyll_export->get_posts();
    $post = get_post($posts[1]);
    $content = $jekyll_export->convert_content($post);
    $this->assertEquals("This is a test **post**.", $content);
  }

  function test_init_temp_dir() {
    global $jekyll_export;
    $jekyll_export->init_temp_dir();
    $this->assertTrue(file_exists($jekyll_export->dir));
    $this->assertTrue(file_exists($jekyll_export->dir . "/_posts"));
  }

  function test_convert_posts() {
    global $jekyll_export;
    $jekyll_export->init_temp_dir();
    $posts = $jekyll_export->convert_posts();
    $post = $jekyll_export->dir . "/_posts/2014-01-01-test-post.md";

    // write the file to the temp dir
    $this->assertTrue(file_exists($post));

    // writes the file contents
    $contents = file_get_contents($post);
    $this->assertContains("title: Test Post", $contents);

    // writes valid YAML
    $parts = explode("---", $contents);
    $this->assertEquals(3,count($parts));
    $yaml = spyc_load($parts[1]);
    $this->assertNotEmpty($yaml);

    // writes the front matter
    $this->assertEquals("Test Post", $yaml["title"]);
    $this->assertEquals("Tester", $yaml["author"]);
    $this->assertEquals("post", $yaml["layout"]);
    $this->assertEquals("/?p=17", $yaml["permalink"]);
    $this->assertEquals(array(0 => "Testing"), $yaml["categories"]);
    $this->assertEquals(array(0 => "tag1", 1 => "tag2"), $yaml["tags"]);

    // writes the post body
    $this->assertEquals("\nThis is a test **post**.", $parts[2]);
  }

  function test_export_options() {
    global $jekyll_export;
    $jekyll_export->init_temp_dir();
    $jekyll_export->convert_options();
    $config = $jekyll_export->dir . "/_config.yml";

    // write the file to the temp dir
    $this->assertTrue(file_exists($config));

    // writes the file content
    $contents = file_get_contents($config);
    $this->assertContains("description: Just another WordPress site", $contents);

    // writes valid YAML
    $yaml = spyc_load($contents);
    $this->assertEquals("Just another WordPress site", $yaml["description"]);
    $this->assertEquals("http://example.org", $yaml["url"]);
    $this->assertEquals("Test Blog", $yaml["name"]);
  }

  function test_write() {
    global $jekyll_export;
    $jekyll_export->init_temp_dir();
    $posts = $jekyll_export->get_posts();
    $post = get_post($posts[1]);
    $jekyll_export->write("Foo", $post);
    $post = $jekyll_export->dir . "/_posts/2014-01-01-test-post.md";
    $this->assertTrue(file_exists($post));
    $this->assertEquals("Foo",file_get_contents($post));
  }

}