<?php
// This file loads our Groq API credentials from a .env file
// We keep secrets in .env so they don't accidentally end up in version control

// reads the .env file line by line and puts each key=value pair into $_ENV and putenv()
// so we can access them with getenv() anywhere in the app
function loadEnv($path) {
    // if the .env file doesn't exist just do nothing (won't crash the app)
    if (!file_exists($path)) return;

    // read every non-empty line
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // skip comment lines that start with #
        if (strpos(trim($line), '#') === 0) continue;

        // split on the first = sign (value might contain = signs itself)
        list($name, $value) = explode('=', $line, 2);

        // store in both $_ENV and system environment
        $_ENV[trim($name)] = trim($value);
        putenv(trim($name) . "=" . trim($value));
    }
}

// load the .env file - it lives two levels up from this config folder
loadEnv(__DIR__ . '/../../.env');

// store the Groq API key as a constant so it's accessible everywhere
// if it's not set in .env this will just be an empty string
define('GROQ_API_KEY', getenv('GROQ_API_KEY'));

// which Groq model to use - defaults to llama-3.3-70b if not set in .env
define('GROQ_MODEL', getenv('GROQ_MODEL') ?: 'llama-3.3-70b-versatile');
