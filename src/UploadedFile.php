<?php

declare (strict_types=1);
namespace Guzzle_Http\Psr7;

use InvalidArgumentException;
use Psr\Http\Message\Stream_Interface;
use Psr\Http\Message\Uploaded_File_Interface;
use RuntimeException;
class Uploaded_File implements Uploaded_File_Interface
{
    private const ERROR_MAP = [UPLOAD_ERR_OK => 'UPLOAD_ERR_OK', UPLOAD_ERR_INI_SIZE => 'UPLOAD_ERR_INI_SIZE', UPLOAD_ERR_FORM_SIZE => 'UPLOAD_ERR_FORM_SIZE', UPLOAD_ERR_PARTIAL => 'UPLOAD_ERR_PARTIAL', UPLOAD_ERR_NO_FILE => 'UPLOAD_ERR_NO_FILE', UPLOAD_ERR_NO_TMP_DIR => 'UPLOAD_ERR_NO_TMP_DIR', UPLOAD_ERR_CANT_WRITE => 'UPLOAD_ERR_CANT_WRITE', UPLOAD_ERR_EXTENSION => 'UPLOAD_ERR_EXTENSION'];
    /**
     * @var string|null
     */
    private $client_filename;
    /**
     * @var string|null
     */
    private $client_media_type;
    /**
     * @var int
     */
    private $error;
    /**
     * @var string|null
     */
    private $file;
    /**
     * @var bool
     */
    private $moved = false;
    /**
     * @var int|null
     */
    private $size;
    /**
     * @var StreamInterface|null
     */
    private $stream;
    /**
     * @param StreamInterface|string|resource $streamOrFile
     */
    public function __construct($stream_or_file, ?int $size, int $error_status, ?string $client_filename = null, ?string $client_media_type = null)
    {
        $this->set_error($error_status);
        $this->size = $size;
        $this->client_filename = $client_filename;
        $this->client_media_type = $client_media_type;
        if ($this->is_ok()) {
            $this->set_stream_or_file($stream_or_file);
        }
    }
    /**
     * Depending on the value set file or stream variable
     *
     * @param StreamInterface|string|resource $streamOrFile
     *
     * @throws InvalidArgumentException
     */
    private function set_stream_or_file($stream_or_file): void
    {
        if (is_string($stream_or_file)) {
            $this->file = $stream_or_file;
        } elseif (is_resource($stream_or_file)) {
            $this->stream = new Stream($stream_or_file);
        } elseif ($stream_or_file instanceof Stream_Interface) {
            $this->stream = $stream_or_file;
        } else {
            throw new InvalidArgumentException('Invalid stream or file provided for UploadedFile');
        }
    }
    /**
     * @throws InvalidArgumentException
     */
    private function set_error(int $error): void
    {
        if (!isset(Uploaded_File::ERROR_MAP[$error])) {
            throw new InvalidArgumentException('Invalid error status for UploadedFile');
        }
        $this->error = $error;
    }
    private static function is_string_not_empty($param): bool
    {
        return is_string($param) && false === empty($param);
    }
    /**
     * Return true if there is no upload error
     */
    private function is_ok(): bool
    {
        return $this->error === UPLOAD_ERR_OK;
    }
    public function is_moved(): bool
    {
        return $this->moved;
    }
    /**
     * @throws RuntimeException if is moved or not ok
     */
    private function validate_active(): void
    {
        if (false === $this->is_ok()) {
            throw new RuntimeException(\sprintf('Cannot retrieve stream due to upload error (%s)', self::ERROR_MAP[$this->error]));
        }
        if ($this->is_moved()) {
            throw new RuntimeException('Cannot retrieve stream after it has already been moved');
        }
    }
    public function get_stream(): Stream_Interface
    {
        $this->validate_active();
        if ($this->stream instanceof Stream_Interface) {
            return $this->stream;
        }
        /** @var string $file */
        $file = $this->file;
        return new Lazy_Open_Stream($file, 'r+');
    }
    public function move_to($target_path): void
    {
        $this->validate_active();
        if (false === self::is_string_not_empty($target_path)) {
            throw new InvalidArgumentException('Invalid path provided for move operation; must be a non-empty string');
        }
        if ($this->file) {
            $this->moved = PHP_SAPI === 'cli' ? rename($this->file, $target_path) : move_uploaded_file($this->file, $target_path);
        } else {
            Utils::copy_to_stream($this->get_stream(), new Lazy_Open_Stream($target_path, 'w'));
            $this->moved = true;
        }
        if (false === $this->moved) {
            throw new RuntimeException(sprintf('Uploaded file could not be moved to %s', $target_path));
        }
    }
    public function get_size(): ?int
    {
        return $this->size;
    }
    public function get_error(): int
    {
        return $this->error;
    }
    public function get_client_filename(): ?string
    {
        return $this->client_filename;
    }
    public function get_client_media_type(): ?string
    {
        return $this->client_media_type;
    }
}