<?php
/**
 * Class SampleTest
 *
 * @package Infinite_Uploads
 */

/**
 * Sample test case.
 */
class B2_ClientTest extends WP_UnitTestCase {

	var $instance;
	protected $bucketId = '5822983365ad6ee76ef30117';
	protected $bucketName = 'iu-us';

	public function setUp() {
		$this->instance = Infinite_Uploads::get_instance();
	}

	/**
	 * Test upload with content.
	 */
	public function test_upload_file() {

		$file_name = "test_" . rand(1,5000) . ".txt";
		$file_content = md5( microtime() );

		// Upload a file to a bucket. Returns a File object.
		$file = $this->instance->b2()->upload([
			'BucketId' => $this->bucketId,
			'FileName' => $file_name,
			'Body' => $file_content
		]);

		$this->assertTrue( true );

		$this->instance->b2()->deleteFile([
			'FileId' => $file->fileId
		]);
	}

	/**
	 * Test upload from filesystem.
	 */
	public function test_upload_file_fs() {

		$file_name = "/tmp/test_" . rand(1,5000) . ".txt";
		$file_content = md5( microtime() );

		file_put_contents( $file_name, $file_content );
		$this->assertFileExists($file_name);

		// Upload a file to a bucket. Returns a File object.
		$file = $this->instance->b2()->upload([
			'BucketId' => $this->bucketId,
			'FileName' => $file_name,

			// The file content can also be provided via a resource.
			'Body' => file_get_contents( $file_name )
		]);

		$this->assertTrue( true );

		$this->instance->b2()->deleteFile([
			'FileId' => $file->fileId
		]);
		@unlink( $file_name );
	}

	/**
	 * A single example test.
	 */
	public function test_list_files() {

		$file_name = "test_" . rand(1,5000) . ".txt";
		$file_content = md5( microtime() );

		// Upload a file to a bucket. Returns a File object.
		$file = $this->instance->b2()->upload([
			'BucketId' => $this->bucketId,
			'FileName' => $file_name,
			'Body' => $file_content
		]);

		// Retrieve an array of file objects from a bucket.
		$fileList = $this->instance->b2()->listFiles([
			'BucketId' => $this->bucketId
		]);

		$this->assertContains( $file->fileName, wp_list_pluck( $fileList, 'fileName' ) );

		$this->instance->b2()->deleteFile([
			'FileId' => $file->fileId
		]);
	}

	/**
	 * Download file by fileid.
	 */
	public function test_download_file_by_id() {

		$file_name = "test_" . rand(1,5000) . ".txt";
		$file_content = md5( microtime() );

		// Upload a file to a bucket. Returns a File object.
		$file = $this->instance->b2()->upload([
			'BucketId' => $this->bucketId,
			'FileName' => $file_name,
			'Body' => $file_content
		]);

		// Download a file from a bucket. Returns the file content.
		$fileContent = $this->instance->b2()->download([
			'FileId' => $file->fileId
		]);


		$this->assertEquals( $fileContent, $file_content );

		$this->instance->b2()->deleteFile([
			'FileId' => $file->fileId
		]);
	}

	/**
	 * Download file by file name.
	 */
	public function test_download_file_by_name() {

		$file_name = "test_" . rand(1,5000) . ".txt";
		$file_content = md5( microtime() );

		// Upload a file to a bucket. Returns a File object.
		$file = $this->instance->b2()->upload([
			'BucketId' => $this->bucketId,
			'FileName' => $file_name,
			'Body' => $file_content
		]);

		// Download a file from a bucket. Returns the file content.
		$fileContent = $this->instance->b2()->download([
			'BucketId' => $this->bucketId,
			'FileName' => $file->fileName
		]);

		$this->assertEquals( $fileContent, $file_content );

		$this->instance->b2()->deleteFile([
			'FileId' => $file->fileId
		]);
	}

	/**
	 * Download file to filesystem.
	 */
	public function test_download_file_to_fs() {

		$file_name = "test_" . rand(1,5000) . ".txt";
		$file_content = md5( microtime() );

		// Upload a file to a bucket. Returns a File object.
		$file = $this->instance->b2()->upload([
			'BucketId' => $this->bucketId,
			'FileName' => $file_name,
			'Body' => $file_content
		]);

		// Download a file from a bucket. Returns the file content.
		$this->instance->b2()->download([
			'FileId' => $file->fileId,
			// Can also save directly to a location on disk. This will cause download() to not return file content.
			'SaveAs' => '/tmp/' . $file_name
		]);

		$this->assertFileExists('/tmp/' . $file_name);
		$this->assertEquals( file_get_contents( '/tmp/' . $file_name ), $file_content );

		$this->instance->b2()->deleteFile([
			'FileId' => $file->fileId
		]);
		@unlink( '/tmp/' . $file_name );
	}

	/**
	 * Delete a file by it's name.
	 */
	public function test_delete_file_name() {

		$file_name = "test_" . rand(1,5000) . ".txt";
		$file_content = md5( microtime() );

		// Upload a file to a bucket. Returns a File object.
		$file = $this->instance->b2()->upload([
			'BucketId' => $this->bucketId,
			'FileName' => $file_name,
			'Body' => $file_content
		]);

		// Delete a file from a bucket. Returns true or false.
		$fileDelete = $this->instance->b2()->deleteFile([
			// Can also identify the file via bucket and path:
			'BucketName' => $this->bucketName,
			'FileName' => $file_name,
		]);

		$this->assertTrue( $fileDelete );
	}
}
