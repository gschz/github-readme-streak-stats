<?php

declare(strict_types=1);

// load functions
$root1 = dirname(__DIR__, 2) . "/vendor/autoload.php";
$root2 = dirname(__DIR__, 1) . "/vendor/autoload.php";
$autoload = is_readable($root1) ? $root1 : $root2;

require_once $autoload;
require_once "stats.php";
require_once "card.php";
require_once "whitelist.php";

if (getenv("VERCEL")) {
    ini_set("display_errors", "0");
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
}

// load environment variables from platform into $_SERVER
$token = getenv("TOKEN");
if ($token !== false && $token !== "") {
    $_SERVER["TOKEN"] = $token;
}
for ($i = 2; $i <= 20; $i++) {
    $t = getenv("TOKEN{$i}");
    if ($t === false || $t === "") {
        break;
    }
    $_SERVER["TOKEN{$i}"] = $t;
}
$whitelist = getenv("WHITELIST");
if ($whitelist !== false && $whitelist !== "") {
    $_SERVER["WHITELIST"] = $whitelist;
}

// load .env
$dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__, 1));
$dotenv->safeLoad();

// if environment variables are not loaded, display error
if (!isset($_SERVER["TOKEN"])) {
    $message = file_exists(dirname(__DIR__ . "../.env", 1))
        ? "Missing token in config. Check Contributing.md for details."
        : ".env was not found. Check Contributing.md for details.";
    renderOutput($message, 500);
}

// set cache to refresh once per three hours and enable CDN caching
$cacheSeconds = 3 * 60 * 60;
header("Expires: " . gmdate("D, d M Y H:i:s", time() + $cacheSeconds) . " GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: public, max-age={$cacheSeconds}, s-maxage={$cacheSeconds}, stale-while-revalidate=60");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer");

// ETag keyed by query + cache window to allow fast 304 responses
$etagWindow = intval(floor(time() / $cacheSeconds));
$etag = '"' . sha1($etagWindow . "|" . ($_SERVER["QUERY_STRING"] ?? "")) . '"';
header("ETag: {$etag}");
if (isset($_SERVER["HTTP_IF_NONE_MATCH"]) && trim($_SERVER["HTTP_IF_NONE_MATCH"]) === $etag) {
    http_response_code(304);
    exit();
}

// require user parameter
if (!isset($_REQUEST["user"])) {
    renderOutput("Missing 'user' parameter.", 400);
}

try {
    // get streak stats for user given in query string
    $user = preg_replace("/[^a-zA-Z0-9\-]/", "", $_REQUEST["user"]);
    // sanitize type to supported values
    $type = $_REQUEST["type"] ?? "svg";
    $_REQUEST["type"] = in_array($type, ["svg", "json"]) ? $type : "svg";
    // early whitelist check to avoid unnecessary API calls
    if (!isWhitelisted($user)) {
        renderOutput("User not in whitelist.", 403);
    }
    // clamp starting_year to valid range
    $startingYear = isset($_REQUEST["starting_year"]) ? intval($_REQUEST["starting_year"]) : null;
    if ($startingYear !== null) {
        $currentYear = intval(date("Y"));
        $startingYear = max(2005, min($startingYear, $currentYear));
    }
    $contributionGraphs = getContributionGraphs($user, $startingYear);
    $contributions = getContributionDates($contributionGraphs);
    if (isset($_GET["mode"]) && $_GET["mode"] === "weekly") {
        $stats = getWeeklyContributionStats($contributions);
    } else {
        // split and normalize excluded days
        $excludeDays = normalizeDays(explode(",", $_GET["exclude_days"] ?? ""));
        $stats = getContributionStats($contributions, $excludeDays);
    }
    renderOutput($stats);
} catch (InvalidArgumentException | AssertionError $error) {
    error_log("Error {$error->getCode()}: {$error->getMessage()}");
    if ($error->getCode() >= 500) {
        error_log($error->getTraceAsString());
    }
    renderOutput($error->getMessage(), $error->getCode());
}
