<?php

declare(strict_types=1);

namespace NewSong\SermonFormatter\Fieldtypes;

use Statamic\Fields\Fieldtype;

class SermonSource extends Fieldtype
{
    protected $icon = 'file-content-list';

    public static function title()
    {
        return 'Sermon Source';
    }

    public function defaultValue()
    {
        return [
            'status' => null,
            'file_name' => null,
            'processed_at' => null,
            'error' => null,
            'log_id' => null,
        ];
    }

    public function preProcess($data)
    {
        if (! is_array($data)) {
            return $this->defaultValue();
        }

        return array_merge($this->defaultValue(), $data);
    }

    public function process($data)
    {
        if (! is_array($data)) {
            return $this->defaultValue();
        }

        return $data;
    }

    public function preload()
    {
        $entryId = null;
        $collection = null;

        try {
            $entry = $this->field->parent();

            if ($entry && method_exists($entry, 'id') && $entry->id()) {
                $entryId = $entry->id();
                if (method_exists($entry, 'collectionHandle')) {
                    $collection = $entry->collectionHandle();
                } elseif (method_exists($entry, 'collection')) {
                    $collection = $entry->collection()?->handle();
                }
            }
        } catch (\Exception $e) {
            // Entry not ready
        }

        return [
            'entryId' => $entryId,
            'collection' => $collection,
            'uploadUrl' => cp_route('sermon-formatter.upload'),
            'statusUrl' => cp_route('sermon-formatter.status', ['entryId' => '__ENTRY_ID__']),
            'reprocessUrl' => cp_route('sermon-formatter.reprocess', ['entryId' => '__ENTRY_ID__']),
            'maxFileSize' => config('sermon-formatter.processing.max_file_size', 10),
            'allowedExtensions' => config('sermon-formatter.processing.allowed_extensions', ['docx', 'rtf']),
        ];
    }

    public function configFieldItems(): array
    {
        return [
            'target_field' => [
                'display' => 'Target Bard Field',
                'instructions' => 'The handle of the Bard field to write formatted content into.',
                'type' => 'text',
                'default' => 'notes',
            ],
        ];
    }
}
