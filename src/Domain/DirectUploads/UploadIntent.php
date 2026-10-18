<?php

namespace SprayMedia\Domain\DirectUploads;

/**
 * A file a client asks to upload directly, as it describes it in step 1:
 * the name and type it gives, and the size it promises. Step 3 holds the
 * file to that size, and reads its real type from its contents.
 *
 * @package SprayMedia\Domain\DirectUploads
 */
final class UploadIntent
{
    /**
     * @param string $filename Sanitized name, without the extension.
     * @param string $extension Letters and digits only, lower case; may be empty.
     * @param string $mimeType The type the client declares.
     * @param int $size The size the client declares, in bytes.
     * @param string|null $customFilename A name to store instead of the file's own.
     * @param array<string, mixed> $input The rest of the validated request (fields of 'upload.custom_rules').
     */
    public function __construct(
        public readonly string $filename,
        public readonly string $extension,
        public readonly string $mimeType,
        public readonly int $size,
        public readonly ?string $customFilename = null,
        public readonly array $input = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'filename' => $this->filename,
            'extension' => $this->extension,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
            'custom_filename' => $this->customFilename,
            'input' => $this->input,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['filename'],
            (string) $data['extension'],
            (string) $data['mime_type'],
            (int) $data['size'],
            $data['custom_filename'] ?? null,
            $data['input'] ?? [],
        );
    }
}
