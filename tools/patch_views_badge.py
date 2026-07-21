from pathlib import Path

root = Path(__file__).resolve().parents[1]
badge = "<?php require IA_ROOT . '/includes/partials/listing-views-badge.php'; ?>\n"

files_ops = [
    (
        root / "index.php",
        [
            (
                "                            <?php endif; ?>\n                            <?php if ($thumbsAttr): ?>",
                "                            <?php endif; ?>\n                            " + badge + "                            <?php if ($thumbsAttr): ?>",
                1,
            ),
            (
                '<motion class="ia-card-actions">',
                '<div class="ia-card-actions">',
            ),
        ],
    ),
    (
        root / "catalog.php",
        [
            (
                "                                        <?php endif; ?>\n                                        <?php if ($thumbsAttr): ?>",
                "                                        <?php endif; ?>\n                                        " + badge.replace("                            ", "                                        ") + "                                        <?php if ($thumbsAttr): ?>",
                1,
            ),
        ],
    ),
    (
        root / "favorites.php",
        [
            (
                "                                <?php if ($thumbsAttr): ?>",
                badge.replace("                            ", "                                ") + "                                <?php if ($thumbsAttr): ?>",
                1,
            ),
        ],
    ),
]

for path, ops in files_ops:
    t = path.read_text(encoding="utf-8")
    for op in ops:
        if len(op) == 2:
            old, new = op
            count = -1
        else:
            old, new, count = op
        if old not in t:
            print(path.name, "SKIP (not found):", old[:60])
            continue
        t = t.replace(old, new, count if count > 0 else t.count(old) if count < 0 else count)
    path.write_text(t, encoding="utf-8", newline="\n")
    print(path.name, "OK")
