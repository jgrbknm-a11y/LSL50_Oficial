<?php

namespace Lsl50\Scorer;

use PDO;

final class Auth
{
  public static function handle(PDO $pdo): string
  {
    $message = "";

    if (post("action") === "login") {
      if (hash_equals(lsl_scorer_pin($pdo), (string)post("pin"))) {
        $_SESSION["lsl50_scorer_ok"] = true;
        header("Location: /scorer/");
        exit;
      }
      $message = "PIN incorrecto";
    }

    if (post("action") === "logout") {
      unset($_SESSION["lsl50_scorer_ok"]);
      header("Location: /scorer/");
      exit;
    }

    return $message;
  }

  public static function loggedIn(): bool
  {
    return !empty($_SESSION["lsl50_scorer_ok"]);
  }
}
