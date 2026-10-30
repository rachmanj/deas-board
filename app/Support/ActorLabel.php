<?php

namespace App\Support;

use App\Models\Session;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Throwable;

class ActorLabel
{
    public static function label(?string $type, ?int $id): string
    {
        if ($type === null || $type === '') {
            return 'Sistem';
        }

        if ($id === null) {
            return 'Sistem';
        }

        try {
            $class = Relation::getMorphedModel($type);
            if ($class === null && class_exists($type)) {
                $class = $type;
            }
            if ($class === null) {
                return 'Tidak dikenal';
            }

            $model = $class::query()->find($id);
            if ($model === null) {
                return 'Tidak dikenal';
            }

            if ($model instanceof Session) {
                return $model->label.' ('.$model->topic_id.')';
            }

            if ($model instanceof User) {
                return $model->name;
            }

            return 'Tidak dikenal';
        } catch (Throwable) {
            return 'Tidak dikenal';
        }
    }
}
