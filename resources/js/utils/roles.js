// Role names shown to people, and which roles count as another one.

const ALSO_COUNTS_AS = {
  tmdu_head:      ['tmdu_staff'],
  dean:           ['dean_secretary'],
  dean_secretary: ['dean'],
};

export function roleIn(role, list) {
  if (!role || !Array.isArray(list)) return false;
  return list.includes(role) || (ALSO_COUNTS_AS[role] || []).some(r => list.includes(r));
}

export const ROLE_LABELS = {
  admin:          'Admin / GCU Head',
  gcu_staff:      'GCU Staff',
  sdu_head:       'SDU Head',
  tmdu_head:      'TMDU Head',
  tmdu_staff:     'TMDU Staff',
  dean:           'Dean',
  dean_secretary: 'Dean',
  dept_chair:     'Department Chair',
  faculty:        'Faculty',
  system_admin:   'System Admin',
};

export const roleLabelOf = role => ROLE_LABELS[role] || role || '';

// The order the leader set for every role list.
export const ROLE_ORDER = ['admin', 'gcu_staff', 'sdu_head', 'tmdu_head', 'tmdu_staff', 'dean', 'dept_chair', 'faculty'];

// What each role may add in User Management. Students are added from the Students page.
export function addableRoles(role) {
  if (role === 'admin') return [...ROLE_ORDER];
  if (role === 'tmdu_head') return ['tmdu_staff'];
  if (role === 'dean' || role === 'dean_secretary') return ['dept_chair', 'faculty'];
  return [];
}
