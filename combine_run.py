# combine_run.py
import os

PROMPT_FILE = "combine_prompt.txt"
OUTPUT_DIR = "combine"
MAX_CHARS = 1024 * 1014  # приблизительный лимит символов на один выходной файл
OUTPUT_PREFIX = "combined_"

CONTINUE_MARK_END = "# --- продолжение следует ---"
CONTINUE_MARK_START = "# --- продолжение файла: {path} ---"

MIN_CUT = 200  # минимальный размер куска, чтобы не зациклиться на крошках


def load_prompt():
    try:
        with open(PROMPT_FILE, "r", encoding="utf-8") as f:
            return f.read()
    except FileNotFoundError:
        print(f"Предупреждение: файл {PROMPT_FILE} не найден, промпт будет пустым")
        return ""
    except Exception as e:
        print(f"Ошибка чтения {PROMPT_FILE}: {e}")
        return ""


def read_file_content(file_path):
    for enc in ("utf-8", "cp1251", "latin-1"):
        try:
            with open(file_path, "r", encoding=enc) as infile:
                return infile.read()
        except UnicodeDecodeError:
            continue
        except Exception as e:
            return f"# ERROR reading file: {str(e)}\n"
    return "# ERROR reading file: unknown encoding\n"


def split_by_lines(text, limit):
    """Режет text на куски не длиннее limit символов по границе строки.

    Если строка сама длиннее limit — режет принудительно.
    Возвращает список кусков.
    """
    parts = []
    start = 0
    n = len(text)
    if limit < 1:
        limit = 1
    while start < n:
        end = start + limit
        if end >= n:
            parts.append(text[start:])
            break
        cut = text.rfind("\n", start, end)
        if cut <= start:
            cut = end
        else:
            cut += 1  # включаем \n
        parts.append(text[start:cut])
        start = cut
    return parts


def build_file_block_header(path, ext, continuation=False):
    suffix = " (продолжение)" if continuation else ""
    return (
        f"# {'=' * 60}\n"
        f"# FILE: {path}{suffix}\n"
        f"# TYPE: {ext.upper()}\n"
        f"# {'=' * 60}\n\n"
    )


def split_block_into_pieces(path, ext, content):
    """Готовит список текстовых кусков для одного файла проекта.

    Каждый кусок гарантированно <= MAX_CHARS по длине (кроме вырожденных
    случаев, когда одна строка длиннее MAX_CHARS — тогда режем принудительно),
    с учётом пометок о продолжении.
    """
    pieces = []
    # Запас под пометки в конце/начале куска
    reserve = max(len(CONTINUE_MARK_END), len(CONTINUE_MARK_START.format(path=path))) + 8

    header_first = build_file_block_header(path, ext, continuation=False)

    # Если всё влезает — один кусок
    full = header_first + content + "\n\n\n"
    if len(full) <= MAX_CHARS:
        return [full]

    # Иначе режем содержимое (без заголовка) на куски,
    # которые вместе с заголовком/пометками влезут в MAX_CHARS.
    # Первый кусок идёт с полным заголовком, остальные — с пометкой о продолжении.
    # Резерв на заголовки:
    header_reserve_first = len(header_first) + len(CONTINUE_MARK_END) + 8
    header_reserve_cont = len(CONTINUE_MARK_START.format(path=path)) + len(CONTINUE_MARK_END) + 8

    # Ограничение на длину куска содержимого
    body_limit_first = MAX_CHARS - header_reserve_first
    body_limit_cont = MAX_CHARS - header_reserve_cont
    if body_limit_first < MIN_CUT:
        body_limit_first = MIN_CUT
    if body_limit_cont < MIN_CUT:
        body_limit_cont = MIN_CUT

    # Режем содержимое
    body = content
    first = True
    while body:
        limit = body_limit_first if first else body_limit_cont
        chunks = split_by_lines(body, limit)
        head = chunks[0]
        body = "".join(chunks[1:])

        if first:
            prefix = header_first
            first = False
        else:
            prefix = CONTINUE_MARK_START.format(path=path) + "\n\n"

        if body:
            # не последний кусок — добавляем пометку в конце
            piece = prefix + head.rstrip("\n") + "\n\n" + CONTINUE_MARK_END + "\n\n"
        else:
            piece = prefix + head + "\n\n\n"
        pieces.append(piece)

    return pieces


def combine_project_files():
    exclude_files = {"combine_run.py", PROMPT_FILE}
    exclude_paths = ["vendor", "dev", "prepare", "assets"]
    target_extensions = [".js", ".html", ".css", ".php"]

    os.makedirs(OUTPUT_DIR, exist_ok=True)

    prompt = load_prompt()

    project_files = []
    for root, dirs, files in os.walk("."):
        norm_root = os.path.normpath(root)
        if any(substring in norm_root for substring in exclude_paths):
            continue
        out_norm = os.path.normpath(OUTPUT_DIR)
        if norm_root == out_norm or norm_root.startswith(out_norm + os.sep):
            continue
        for file in files:
            file_ext = os.path.splitext(file)[1].lower()
            if file_ext in target_extensions and file not in exclude_files:
                full_path = os.path.join(root, file)
                project_files.append((full_path, file_ext))

    project_files.sort(key=lambda x: (x[1], x[0]))

    # Готовим шапку первой части
    header_lines = [
        "# COMBINED PROJECT FILES FOR DEEPSEEK",
        "# Encoding: UTF-8",
        f"# Processed extensions: {', '.join(target_extensions)}",
        "",
    ]
    header_first = "\n".join(header_lines) + "\n"
    if prompt:
        header_first = prompt + "\n\n" + header_first

    # Разбиваем шапку (если вдруг больше лимита) — иначе один кусок
    header_pieces = split_by_lines(header_first, MAX_CHARS)

    # Укладываем куски в части
    parts = []
    current = ""
    first_part = True

    def push_piece(piece):
        nonlocal current
        if len(current) + len(piece) <= MAX_CHARS or not current:
            current += piece
        else:
            parts.append(current)
            current = piece

    # шапка — только в первую часть
    for hp in header_pieces:
        push_piece(hp)

    for path, ext in project_files:
        content = read_file_content(path)
        pieces = split_block_into_pieces(path, ext, content)
        for piece in pieces:
            push_piece(piece)

    if current:
        parts.append(current)

    if not parts:
        parts = [header_first]

    written = []
    for idx, part_text in enumerate(parts, start=1):
        out_name = f"{OUTPUT_PREFIX}{idx}.txt"
        out_path = os.path.join(OUTPUT_DIR, out_name)
        with open(out_path, "w", encoding="utf-8") as outfile:
            outfile.write(part_text)
        written.append((out_path, len(part_text)))

    print(f"Объединено {len(project_files)} файлов в {len(written)} частей:")
    for out_path, size in written:
        print(f"  {out_path}: {size} символов")

    ext_count = {}
    for _, ext in project_files:
        ext_count[ext] = ext_count.get(ext, 0) + 1
    print("Статистика по типам файлов:")
    for ext, count in ext_count.items():
        print(f"  {ext}: {count} файлов")

    return [w[0] for w in written]


if __name__ == "__main__":
    result_files = combine_project_files()
    print(f"Создано файлов: {len(result_files)}")