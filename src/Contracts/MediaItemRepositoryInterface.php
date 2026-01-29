<?php

namespace SprayMedia\Contracts;

use SprayMedia\Domain\Models\MediaItem;

/**
 * Interface MediaItemRepositoryInterface
 *
 * Defines the contract for the media item data persistence layer.
 * This interface decouples the application's core logic from the specific
 * database implementation (e.g., Eloquent, Doctrine).
 */
interface MediaItemRepositoryInterface
{
    /**
     * Creates and persists a new MediaItem record in the database.
     *
     * @param array<string, mixed> $data The attributes for the new media record.
     * @return MediaItem The newly created MediaItem model instance.
     */
    public function create(array $data): MediaItem;

    /**
     * Retrieves a MediaItem record by its primary key.
     *
     * @param mixed $id The primary key of the MediaItem record.
     * @return MediaItem|null The found MediaItem model instance, or null if not found.
     */
    public function find(mixed $id): ?MediaItem;

    /**
     * Updates an existing MediaItem record in the database.
     *
     * @param mixed $id The primary key of the MediaItem record to update.
     * @param array<string, mixed> $data The new attributes to apply.
     * @return bool Returns true if the record was successfully updated, false otherwise.
     */
    public function update(mixed $id, array $data): bool;

    /**
     * Deletes a MediaItem record from the database by its primary key.
     *
     * @param mixed $id The primary key of the MediaItem record to delete.
     * @return bool Returns true if the record was successfully deleted, false otherwise.
     */
    public function delete(mixed $id): bool;
}
