<?php

enum CommandEnums: string
{
    case LIKE_COMMENT = 'Met un like sur chaque commentaire';
    case ARCHIVE_PRUNE = 'Supprimer les articles archivés';


    public static function toFormChoices(): array
    {
        return [
            self::LIKE_COMMENT->value => self::LIKE_COMMENT,
            self::ARCHIVE_PRUNE->value => self::ARCHIVE_PRUNE,
        ];
    }

    public static function toCommand(CommandEnums $commandEnums): string
    {
        return match ($commandEnums) {
            self::LIKE_COMMENT => "app:comment:like",
            self::ARCHIVE_PRUNE => "app:article:prune",
            default => throw new Exception("Unknown command enum '{$commandEnums}'"),
        };
    }
}