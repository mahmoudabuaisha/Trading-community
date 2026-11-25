import { t } from '../i18n'
import type { User } from '../types/domain'

/** The label shown under a member's name: the title they wear from the store, otherwise their standing. */
export function roleName(user: Pick<User, 'role' | 'isPremium' | 'title'> | null | undefined): string {
    if (!user) return ''
    if (user.title) return user.title
    if (user.role === 'admin') return t('role.admin')
    if (user.role === 'moderator') return t('role.moderator')
    return user.isPremium ? t('role.premium') : t('role.user')
}
