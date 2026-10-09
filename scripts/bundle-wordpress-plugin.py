"""Generate a single-file Smitharia Core for the WordPress plugin editor."""
import json
import re
import sys
from pathlib import Path

root = Path(__file__).resolve().parents[1] / "wordpress/wp-content/plugins/smitharia-core"
source = (root / "smitharia-core.php").read_text()
data = json.dumps(json.loads((root / "data/project-details.json").read_text()), ensure_ascii=False)


def inline(match):
    content = (root / match.group(1)).read_text().removeprefix("<?php").strip()
    if match.group(1).endswith("class-smitharia-project-defaults.php"):
        literal = "'" + data.replace("\\", "\\\\").replace("'", "\\'") + "'"
        content = content.replace("file_get_contents(SMITHARIA_CORE_DIR . 'data/project-details.json')", literal)
    return content


source = re.sub(r"require_once SMITHARIA_CORE_DIR \. '([^']+)';", inline, source)
Path(sys.argv[1]).write_text(source)
