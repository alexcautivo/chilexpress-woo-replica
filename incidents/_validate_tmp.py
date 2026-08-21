import json
import sys
from pathlib import Path
from jsonschema import Draft202012Validator

root = Path(__file__).resolve().parent
schema_path = root / "schema" / "incident.schema.json"
schema = json.loads(schema_path.read_text(encoding="utf-8"))
Draft202012Validator.check_schema(schema)
validator = Draft202012Validator(schema)

files = [
    schema_path,
    root / "templates" / "para-el-cliente.json",
    root / "tickets" / "SR-108688.json",
    root / "tickets" / "_EJEMPLO-1.1.json",
]
failed = 0
print("schema meta: Draft202012 OK")
for f in files:
    data = json.loads(f.read_text(encoding="utf-8"))
    rel = f.as_posix()
    if f == schema_path:
        print(f"{rel}: parse_ok (schema file, skip instance validate)")
        continue
    errs = sorted(validator.iter_errors(data), key=lambda e: list(e.path))
    if not errs:
        print(f"{rel}: parse_ok schema_ok")
    else:
        failed += 1
        print(f"{rel}: parse_ok schema_FAIL ({len(errs)})")
        for e in errs:
            loc = ".".join(str(p) for p in e.absolute_path) or "(root)"
            print(f"  - {loc}: {e.message}")
sys.exit(failed)
