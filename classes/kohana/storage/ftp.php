<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Native FTP driver for Storage Module
 * 
 * @package		Storage
 * @category	Base
 * @author		Micheal Morgan <micheal@morgan.ly>
 * @copyright	(c) 2011 Micheal Morgan
 * @license		MIT
 */
class Kohana_Storage_Ftp extends Storage
{	
	/**
	 * Default config
	 * 
	 * @access	protected
	 * @var		array
	 */
	protected $_config = array
	(
		'host'		=> NULL,
		'username'	=> NULL,
		'password'	=> NULL,
		'url'		=> NULL,
		'port'		=> 21,
		'timeout'	=> 90,
		'passive'	=> FALSE,
		'ssl'		=> TRUE,
		'transfer'	=> FTP_BINARY
	);

	/**
	 * FTP Connection
	 * 
	 * @access	protected
	 * @var		ftp_stream
	 */
	protected $_connection;
	
	/**
	 * Cache origin directory for resetting back after directory operations.
	 * 
	 * @access	protected
	 * @var		string
	 */
	protected $_origin = '/';
	
	/**
	 * Load connection
	 * 
	 * @access	protected
	 * @return	void
	 */
	protected function _load()
	{
		if ($this->_connection === NULL)
		{
			$call = $this->_config['ssl'] ? 'ftp_connect' : 'ftp_ssl_connect';
			
			$this->_connection = $call($this->_config['host'], $this->_config['port'], $this->_config['timeout']);
			
			if ( ! ftp_login($this->_connection, $this->_config['username'], $this->_config['password']))
				throw new Kohana_Exception('Storage FTP driver failed to authenticate.');
				
			if ($this->_config['passive'])
			{
				ftp_pasv($this->_connection, TRUE);	
			}
				
			$this->_origin = ftp_pwd($this->_connection);
		}	
		
		return $this->_connection;
	}
	
	/**
	 * If set, terminate connection during destruct
	 * 
	 * @access	public
	 * @return	void
	 */
	public function __destruct() 
	{
		if ($this->_connection)
		{
			ftp_close($this->_connection);
		}
	}	
   
	/**
	 * Set
	 * 
	 * @access	protected
	 * @param	string
	 * @param	resource
	 * @return	void
	 */
	protected function _set($path, $handle)
	{
		$this->_load();

		$this->_create_directory($path);

		ftp_fput($this->_connection, $path, $handle, $this->_config['transfer']);
	}
	
	/**
	 * Get
	 * 
	 * @access	protected
	 * @param	string
	 * @param	resource
	 * @return	bool
	 */
	protected function _get($path, $handle)
	{
		$this->_load();
		
		return ftp_fget($this->_connection, $handle, $path, $this->_config['transfer']);
	}	
	
	/**
	 * Delete
	 * 
	 * @access	protected
	 * @param	string
	 * @return	bool
	 */
	protected function _delete($path)
	{
		$this->_load();

		return ftp_delete($this->_connection, $path);
	}			
	
	/**
	 * Size
	 * 
	 * @access	protected
	 * @param	string
	 * @return	int
	 */
	protected function _size($path)
	{
		$this->_load();
		
		$size = ftp_size($this->_connection, $path);
		
		if ($size < 0)
			return 0;
		else
			return $size;
	}
	
	/**
	 * Whether or not file exists
	 * 
	 * @access	protected
	 * @param	string
	 * @return	bool
	 */
	protected function _exists($path)
	{
		$this->_load();

		return ftp_size($this->_connection, $path) > -1;
	}
	
	/**
	 * Get URL
	 * 
	 * FTP only supports public files (in contrast to drivers like S3 and Cloud Files)
	 * 
	 * @access	protected
	 * @param	string	Path of file 
	 * @return	bool|string
	 */
	protected function _url($path, $protocol)
	{
		$this->_load();
		
		if ( ! $this->_config['url'])
			return FALSE;
			
		return $protocol . '://' . $this->_config['url'] . $path;	
	}
	
	/**
	 * Create directory based on current location
	 * 
	 * @access	protected
	 * @param	string
	 * @return	bool
	 */
	protected function _create_directory($path)
	{
		$result = TRUE;

		$segments = explode('/', $path);		
		
		$path = '';
		
		foreach ($segments as $segment)
		{
			// Skip files
			if (strpos($segment, '.'))
				break;
			
			$path .= '/' . $segment;

			// Create directory in relation to root if unable to change directory.
			if ( ! @ftp_chdir($this->_connection, $path))
			{
				ftp_chdir($this->_connection, '/');
	
				if ( ! ftp_mkdir($this->_connection, $path))
				{
					$result = FALSE;
				}
			}
		}

		ftp_chdir($this->_connection, $this->_origin);
		
		return $result;
	}	
}