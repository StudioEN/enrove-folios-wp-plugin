(function (wp) {
  'use strict';

  if (!wp || !wp.blocks || !wp.element || !wp.blockEditor) {
    return;
  }

  var el = wp.element.createElement;
  var useState = wp.element.useState;
  var useCallback = wp.element.useCallback;
  var registerBlockType = wp.blocks.registerBlockType;
  var RichText = wp.blockEditor.RichText;
  var useBlockProps = wp.blockEditor.useBlockProps;
  var BlockControls = wp.blockEditor.BlockControls;
  var MediaUpload = wp.blockEditor.MediaUpload;
  var ToolbarGroup = wp.components.ToolbarGroup;
  var ToolbarButton = wp.components.ToolbarButton;

  var DEFAULT_MEMBERS = [
    { name: 'Sarah Chen',     role: 'Lead Designer',      bio: 'Leads design strategy and visual direction across the engagement.', photo: '' },
    { name: 'Marcus Rivera',  role: 'Project Manager',    bio: 'Ensures timely delivery and clear communication at every milestone.', photo: '' },
    { name: 'Aiko Tanaka',   role: 'Senior Developer',   bio: 'Oversees technical implementation, performance, and quality assurance.', photo: '' },
  ];

  var MIN_MEMBERS = 1;
  var MAX_MEMBERS = 12;

  // ── Helpers ────────────────────────────────────────────────────────────

  function getInitials(name) {
    if (!name) return '?';
    var parts = name.replace(/<[^>]*>/g, '').trim().split(/\s+/);
    if (parts.length === 0) return '?';
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
  }

  // ── Inline styles ──────────────────────────────────────────────────────

  // -- List layout (horizontal rows) --
  var listWrapStyle = {
    display: 'flex',
    flexDirection: 'column',
    gap: '0',
    margin: '1rem 0',
    border: '1px solid #d6d5d0',
    borderRadius: '3px',
    overflow: 'hidden',
    background: '#f7f7f5',
  };

  var listCardStyle = {
    display: 'flex',
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: '1rem',
    padding: '1rem 1.25rem',
    borderTop: '1px solid #d6d5d0',
    cursor: 'pointer',
    transition: 'background 150ms ease, box-shadow 150ms ease',
  };

  var listCardFirstStyle = {
    borderTop: 'none',
  };

  var listCopyStyle = {
    flex: '1',
    minWidth: '0',
    display: 'flex',
    flexDirection: 'column',
    gap: '0',
  };

  var listAvatarSize = '48px';
  var listInitialsFontSize = '0.875rem';

  // -- Grid layout (multi-column cards) --
  var gridWrapStyle = {
    display: 'grid',
    gap: '1.5rem',
    margin: '1rem 0',
  };

  var gridCardStyle = {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    textAlign: 'center',
    padding: '1.5rem 1rem',
    border: '1px solid #d6d5d0',
    borderRadius: '3px',
    background: '#f7f7f5',
    cursor: 'pointer',
    transition: 'background 150ms ease, box-shadow 150ms ease',
  };

  var gridAvatarSize = '64px';
  var gridInitialsFontSize = '1.125rem';

  // -- Shared --
  var ACTIVE_BG = '#ededeb';

  var avatarImgStyle = {
    width: '100%',
    height: '100%',
    objectFit: 'cover',
    display: 'block',
  };

  var initialsBaseStyle = {
    width: '100%',
    height: '100%',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    background: '#27498c',
    color: '#ffffff',
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontWeight: '600',
    letterSpacing: '0.02em',
    lineHeight: '1',
  };

  var avatarOverlayStyle = {
    position: 'absolute',
    inset: '0',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    background: 'rgba(0,0,0,0.4)',
    color: '#fff',
    fontSize: '0.625rem',
    fontWeight: '600',
    textTransform: 'uppercase',
    letterSpacing: '0.08em',
    opacity: '0',
    transition: 'opacity 150ms ease',
    borderRadius: '50%',
  };

  var avatarOverlayVisibleStyle = Object.assign({}, avatarOverlayStyle, {
    opacity: '1',
  });

  var nameStyle = {
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.875rem',
    fontWeight: '500',
    color: '#1b1a18',
    lineHeight: '1.35',
    width: '100%',
  };

  var roleStyle = {
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.75rem',
    fontWeight: '600',
    color: '#666259',
    textTransform: 'uppercase',
    letterSpacing: '0.09em',
    lineHeight: '1.3',
    marginTop: '0.15rem',
    width: '100%',
  };

  var bioStyle = {
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.75rem',
    color: '#666259',
    lineHeight: '1.55',
    marginTop: '0.5rem',
    width: '100%',
  };

  var BLOCK_ICON = el('svg', { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 24, height: 24 },
    el('path', {
      fill: 'currentColor',
      d: 'M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z',
    })
  );

  // ── Avatar component ───────────────────────────────────────────────────

  function AvatarEditor(props) {
    var photo = props.photo;
    var name = props.name;
    var size = props.size;
    var initialsFontSize = props.initialsFontSize;
    var onSelect = props.onSelect;

    var hoverState = useState(false);
    var hovering = hoverState[0];
    var setHovering = hoverState[1];

    var avatarWrapStyle = {
      position: 'relative',
      width: size,
      height: size,
      borderRadius: '50%',
      overflow: 'hidden',
      flexShrink: '0',
      cursor: 'pointer',
    };

    var currentInitialsStyle = Object.assign({}, initialsBaseStyle, {
      fontSize: initialsFontSize,
    });

    var avatarContent;
    if (photo) {
      avatarContent = el('img', { src: photo, alt: '', style: avatarImgStyle });
    } else {
      avatarContent = el('div', { style: currentInitialsStyle }, getInitials(name));
    }

    return el(MediaUpload, {
      onSelect: function (media) {
        if (media && media.url) { onSelect(media.url); }
      },
      allowedTypes: ['image'],
      render: function (renderProps) {
        return el('div', {
            style: avatarWrapStyle,
            onClick: renderProps.open,
            onMouseEnter: function () { setHovering(true); },
            onMouseLeave: function () { setHovering(false); },
          },
          avatarContent,
          el('span', {
            style: hovering ? avatarOverlayVisibleStyle : avatarOverlayStyle,
          }, photo ? 'Change' : 'Photo')
        );
      },
    });
  }

  // ── Card component ─────────────────────────────────────────────────────

  function EditCard(props) {
    var members = props.members;
    var index = props.index;
    var onChange = props.onChange;
    var isActive = props.isActive;
    var onFocusCard = props.onFocusCard;
    var layout = props.layout;
    var member = members[index];
    var isList = layout === 'list';

    function update(field, val) {
      var updated = members.slice();
      updated[index] = Object.assign({}, member);
      updated[index][field] = val;
      onChange(updated);
    }

    var currentCardStyle;
    if (isList) {
      currentCardStyle = Object.assign({}, listCardStyle);
      if (index === 0) {
        Object.assign(currentCardStyle, listCardFirstStyle);
      }
      if (isActive) {
        currentCardStyle.background = ACTIVE_BG;
        currentCardStyle.boxShadow = 'inset 3px 0 0 #27498c';
      }
    } else {
      currentCardStyle = Object.assign({}, gridCardStyle);
      if (isActive) {
        currentCardStyle.background = ACTIVE_BG;
        currentCardStyle.boxShadow = 'inset 0 3px 0 #27498c';
      }
    }

    var avatarEl = el(AvatarEditor, {
      photo: member.photo,
      name: member.name,
      size: isList ? listAvatarSize : gridAvatarSize,
      initialsFontSize: isList ? listInitialsFontSize : gridInitialsFontSize,
      onSelect: function (url) { update('photo', url); },
    });

    var textEls = [
      el(RichText, {
        key: 'name',
        tagName: 'div',
        style: nameStyle,
        value: member.name,
        onChange: function (v) { update('name', v); },
        placeholder: 'Name',
        allowedFormats: [],
      }),
      el(RichText, {
        key: 'role',
        tagName: 'div',
        style: roleStyle,
        value: member.role,
        onChange: function (v) { update('role', v); },
        placeholder: 'Role',
        allowedFormats: [],
      }),
      el(RichText, {
        key: 'bio',
        tagName: 'div',
        style: bioStyle,
        value: member.bio,
        onChange: function (v) { update('bio', v); },
        placeholder: 'Short bio…',
        allowedFormats: [],
      }),
    ];

    if (isList) {
      return el('div', {
          style: currentCardStyle,
          onMouseDownCapture: function () { onFocusCard(index); },
          onFocusCapture: function () { onFocusCard(index); },
        },
        avatarEl,
        el('div', { style: listCopyStyle }, textEls)
      );
    }

    return el('div', {
        style: currentCardStyle,
        onMouseDownCapture: function () { onFocusCard(index); },
        onFocusCapture: function () { onFocusCard(index); },
      },
      avatarEl,
      textEls
    );
  }

  // ── Block registration ────────────────────────────────────────────────

  registerBlockType('groove-proposal/team-grid', {
    title: 'Team Grid',
    description: 'Team member cards with photo or initials, name, role, and bio. Grid or list layout.',
    icon: BLOCK_ICON,
    category: 'groove-proposal',
    keywords: ['team', 'people', 'grid', 'members', 'staff', 'about'],
    attributes: {
      members: {
        type: 'array',
        default: DEFAULT_MEMBERS,
      },
      layout: {
        type: 'string',
        default: 'grid',
      },
      columns: {
        type: 'number',
        default: 3,
      },
    },
    supports: {
      html: false,
      reusable: true,
    },

    edit: function (props) {
      var attrs = props.attributes;
      var members = attrs.members;
      var layout = attrs.layout;
      var columns = attrs.columns;
      var blockProps = useBlockProps();
      var isList = layout === 'list';

      var activeState = useState(-1);
      var activeIndex = activeState[0];
      var setActiveIndex = activeState[1];

      var onFocusCard = useCallback(function (i) {
        setActiveIndex(i);
      }, []);

      function setMembers(updated) {
        props.setAttributes({ members: updated });
      }

      function addMember() {
        if (members.length >= MAX_MEMBERS) return;
        var newMember = { name: '', role: '', bio: '', photo: '' };
        if (activeIndex >= 0 && activeIndex < members.length) {
          var updated = members.slice();
          updated.splice(activeIndex, 0, newMember);
          setMembers(updated);
          setActiveIndex(activeIndex + 1);
        } else {
          setMembers(members.concat([newMember]));
        }
      }

      function removeMember() {
        if (members.length <= MIN_MEMBERS) return;
        if (activeIndex >= 0 && activeIndex < members.length) {
          var updated = members.slice();
          updated.splice(activeIndex, 1);
          setMembers(updated);
          if (activeIndex >= updated.length) {
            setActiveIndex(updated.length > 0 ? updated.length - 1 : -1);
          }
        } else {
          setMembers(members.slice(0, -1));
        }
      }

      function toggleLayout() {
        props.setAttributes({ layout: isList ? 'grid' : 'list' });
      }

      function toggleColumns() {
        props.setAttributes({ columns: columns === 3 ? 2 : 3 });
      }

      // Build wrapper style based on layout
      var wrapStyle;
      if (isList) {
        wrapStyle = listWrapStyle;
      } else {
        wrapStyle = Object.assign({}, gridWrapStyle, {
          gridTemplateColumns: 'repeat(' + columns + ', 1fr)',
        });
      }

      return el(wp.element.Fragment, null,
        el(BlockControls, null,
          el(ToolbarGroup, null,
            el(ToolbarButton, {
              icon: 'plus-alt2',
              label: activeIndex >= 0 ? 'Insert member before selected' : 'Add member',
              onClick: addMember,
              disabled: members.length >= MAX_MEMBERS,
            }),
            el(ToolbarButton, {
              icon: 'minus',
              label: activeIndex >= 0 ? 'Remove selected member' : 'Remove last member',
              onClick: removeMember,
              disabled: members.length <= MIN_MEMBERS,
            })
          ),
          el(ToolbarGroup, null,
            el(ToolbarButton, {
              icon: isList ? 'grid-view' : 'list-view',
              label: isList ? 'Switch to grid' : 'Switch to list',
              onClick: toggleLayout,
            }),
            !isList ? el(ToolbarButton, {
              icon: 'columns',
              label: columns === 3 ? 'Switch to 2 columns' : 'Switch to 3 columns',
              onClick: toggleColumns,
            }) : null
          )
        ),
        el('div', blockProps,
          el('div', { style: wrapStyle },
            members.map(function (member, i) {
              return el(EditCard, {
                key: i,
                members: members,
                index: i,
                onChange: setMembers,
                isActive: activeIndex === i,
                onFocusCard: onFocusCard,
                layout: layout,
              });
            })
          )
        )
      );
    },

    save: function () {
      return null;
    },
  });
})(window.wp);
