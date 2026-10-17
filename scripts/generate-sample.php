<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
fwrite(STDERR, "Demo generation is disabled. Test fixtures are isolated in tests/fixtures/demo.sql.\n");
exit(1);
