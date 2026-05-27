#!/usr/bin/env python3
"""Generate Thai slugs for repairs missing slug."""
import subprocess, re

CMD = ["docker", "compose", "exec", "-T", "db",
       "mysql", "-ucmns_user", "-pcmns_password", "--default-character-set=utf8mb4", "cmnsfixmac_db"]

def mysql(sql):
    r = subprocess.run(CMD, input=sql.encode("utf-8"), capture_output=True)
    return r.stdout.decode("utf-8", errors="replace")

def fetch(sql):
    r = subprocess.run(CMD + ["-N"], input=sql.encode("utf-8"), capture_output=True)
    rows = []
    for line in r.stdout.decode("utf-8", errors="replace").splitlines():
        rows.append(line.split("\t"))
    return rows

def make_slug(text, model=""):
    # combine title + model for more unique slug
    parts = text.strip()
    if model and model.strip() and model.strip() != "-":
        parts = parts + " " + model.strip()
    # lowercase english, keep thai + alphanum + spaces
    result = ""
    for ch in parts:
        if "฀" <= ch <= "๿":  # Thai range
            result += ch
        elif ch.isascii() and (ch.isalnum() or ch in " -"):
            result += ch.lower()
        elif ch in " /|,;:()[]":
            result += " "
    # collapse spaces → hyphen
    slug = re.sub(r"[\s\-]+", "-", result.strip())
    slug = slug.strip("-")
    # max length 120
    if len(slug) > 120:
        slug = slug[:120].rstrip("-")
    return slug

# Get existing slugs to avoid duplicates
existing = set()
for row in fetch("SELECT slug FROM repairs WHERE slug IS NOT NULL AND slug != '';"):
    if row[0]:
        existing.add(row[0].strip())

# Get all repairs without slug
rows = fetch("SELECT id, title, model FROM repairs WHERE slug IS NULL OR slug='';")
print(f"Generating slugs for {len(rows)} repairs...")

updates = []
for row in rows:
    if len(row) < 3:
        continue
    rid, title, model = row[0].strip(), row[1].strip(), row[2].strip()
    base = make_slug(title, model)
    if not base:
        base = f"repair-{rid}"

    # ensure unique
    slug = base
    i = 2
    while slug in existing:
        slug = f"{base}-{i}"
        i += 1
    existing.add(slug)

    safe = slug.replace("'", "\\'")
    updates.append(f"UPDATE repairs SET slug='{safe}' WHERE id={rid};")
    print(f"  id={rid}: {slug[:60]}")

# Execute all updates
if updates:
    mysql("\n".join(updates))
    print(f"\nDone — updated {len(updates)} slugs")
