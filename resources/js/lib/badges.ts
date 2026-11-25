import { t } from '../i18n'
import type { Badge } from '../types/domain'

/** "Complete 5 tasks + 30 focus minutes", from a badge's conditions. */
export function conditionsText(badge: Pick<Badge, 'conditions'>): string {
    return badge.conditions
        .map((condition) => t(`admin.metricGoal.${condition.metric}`, { n: condition.threshold }))
        .join(' + ')
}
