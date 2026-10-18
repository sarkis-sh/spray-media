<?php

namespace SprayMedia\Domain\DirectUploads;

/**
 * What the direct upload policy settles in step 1, and step 3 carries out:
 * the directory under base_dir the file goes to, the extra attributes the
 * media item is created with, and, for a kind of upload with limits of its
 * own, the size and types step 3 holds the file to instead of the config's
 * 'upload.max_kb' and 'upload.mimetypes'.
 *
 * Everything here travels in the upload token, so the attributes must be
 * JSON-serialisable -- and fillable on the model.
 *
 * @package SprayMedia\Domain\DirectUploads
 */
final class UploadDecision
{
    /**
     * @param string|null $directory Relative to base_dir, as an uploader's $directory.
     * @param array<string, mixed> $attributes Extra columns for the media item.
     * @param int|null $maxKb The largest file allowed, in kilobytes; null: 'upload.max_kb'.
     * @param array<int, string>|null $mimetypes The types allowed; null: 'upload.mimetypes'.
     */
    public function __construct(
        public readonly ?string $directory = null,
        public readonly array $attributes = [],
        public readonly ?int $maxKb = null,
        public readonly ?array $mimetypes = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'directory' => $this->directory,
            'attributes' => $this->attributes,
            'max_kb' => $this->maxKb,
            'mimetypes' => $this->mimetypes,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['directory'] ?? null,
            $data['attributes'] ?? [],
            isset($data['max_kb']) ? (int) $data['max_kb'] : null,
            $data['mimetypes'] ?? null,
        );
    }
}
