export function formatPendingAction(action) {
    return {
        name: action?.name || 'Action',
        argsText: JSON.stringify(action?.args || {}, null, 2),
    };
}
