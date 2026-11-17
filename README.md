# FormatD.NodeTypeSubtreeTag

Automatic SubtreeTag assignment based on NodeType configuration for Neos 9.

## Usage

### Configure NodeType with Subtree Tags

Add `subtree.tags` configuration to your NodeType definition. Tags support flexible configuration with per-tag workspace restrictions:

```yaml
'Your.Package:Document.Example':
  superTypes:
    'Neos.Neos:Document': true
  properties:
    title:
      type: string
  subtree:
    tags:
      - 'my-tag'                              # Simple string: applies in all workspaces
      - tag: 'special-tag'                    # Object format with workspace restriction
        workspaces:
          - 'live'
          - 'user-admin'
```

### Configuration Options

#### `subtree.tags` (required)

Array of tag definitions. Each tag can be configured in three formats:

##### 1. Simple String Format
```yaml
subtree:
  tags:
    - 'my-tag'
    - 'archived'
    - 'custom_tag'
```

Applies the tag in all workspaces.

##### 2. Inline Key-Value Format
```yaml
subtree:
  tags:
    myTag: 'my-tag'
    archived: 'archived'
```

Applies the tag in all workspaces. The key name is ignored, only the value is used.

##### 3. Object Format with Workspace Control
```yaml
subtree:
  tags:
    - tag: 'published'
      workspaces:
        - 'live'
    - tag: 'draft'
      workspaces:
        - 'user-default'
        - 'user-admin'
    - tag: 'always-applied'
      # Omit 'workspaces' to apply in all workspaces
```

Tag names must:
- Be lowercase
- Contain only alphanumeric characters, hyphens, underscores, or dots
- Be 1-36 characters long

The `workspaces` key is optional:
- If omitted, the tag is applied in all workspaces
- If specified, the tag is only applied when a node is created in one of the listed workspaces

### Tagging existing nodes

Tags are only applied on node (variant) creation. To tag nodes that existed before the configuration was added, run:

```bash
./flow subtreetag:apply --workspace live [--dry-run]
```

Already tagged nodes are skipped. Workspaces based on the tagged workspace have to be rebased to see the new tags.

### Error Handling

- **Invalid tag format:** If a tag doesn't match the required pattern, node creation fails with an error
- **NodeType not found:** If the NodeType is missing, tagging is skipped with a warning
- **Invalid workspace filter:** Invalid workspace names in `applyInWorkspaces` are silently filtered

## Examples

### Simple: Apply tag in all workspaces

```yaml
'Vendor.Site:Document.Standard':
  superTypes:
    'Neos.Neos:Document': true
  subtree:
    tags:
      - 'standard-doc'
```

### Apply tags in specific workspaces only

```yaml
'Vendor.Site:Document.Published':
  superTypes:
    'Neos.Neos:Document': true
  subtree:
    tags:
      - tag: 'published'
        workspaces:
          - 'live'
```

### Mixed format: Some tags in all workspaces, some in specific workspaces

```yaml
'Vendor.Site:Document.Mixed':
  superTypes:
    'Neos.Neos:Document': true
  subtree:
    tags:
      - 'always-tagged'                       # Applies in all workspaces
      - tag: 'only-in-live'
        workspaces:
          - 'live'
      - tag: 'draft-only'
        workspaces:
          - 'user-default'
```

### Multiple tags with different workspace restrictions

```yaml
'Vendor.Site:Document.Review':
  superTypes:
    'Neos.Neos:Document': true
  subtree:
    tags:
      - tag: 'under-review'
        workspaces:
          - 'user-admin'
          - 'user-editor'
      - tag: 'published'
        workspaces:
          - 'live'
      - 'internal'                            # All workspaces
```