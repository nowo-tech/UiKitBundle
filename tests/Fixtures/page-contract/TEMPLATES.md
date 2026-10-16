# Templates (contract fixture)

## Pages

| Page | Scaffold |
| --- | --- |
| `items/index.html.twig` | `page/list.html.twig` |

## Unique pages

- `board.html.twig` — kanban board (extends the layout directly).
- `bad/unique_without_layout.html.twig` — negative fixture.
- `bad/no_slots.html.twig`, `bad/no_attrs.html.twig` — negative fixtures.

### Notes

- `bad/empty_content.html.twig` (nested heading: still part of the unique list).

## Data blocks

- `not/unique.html.twig` is outside the unique list.
