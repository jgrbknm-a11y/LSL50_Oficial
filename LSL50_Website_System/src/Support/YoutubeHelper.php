<?php

namespace Lsl50\Support;

final class YoutubeHelper
{
  public static function extractVideoId(string $input): ?string
  {
    $input = trim($input);
    if ($input === "") return null;
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $input)) return $input;
    if (preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/|youtube\.com/live/)([A-Za-z0-9_-]{11})~', $input, $m)) {
      return $m[1];
    }
    return null;
  }

  public static function watchUrl(?string $videoIdOrUrl): ?string
  {
    $id = self::extractVideoId((string)$videoIdOrUrl);
    return $id ? "https://www.youtube.com/watch?v=" . $id : null;
  }

  public static function embedUrl(?string $videoIdOrUrl): ?string
  {
    $id = self::extractVideoId((string)$videoIdOrUrl);
    return $id ? "https://www.youtube.com/embed/" . $id : null;
  }
}
