import { messages, t } from '../i18n'
import type { User } from '../types/domain'

/** The label shown under a member's name: their earned title if equipped, otherwise their standing. */
export function roleName(user: Pick<User, 'role' | 'isPremium' | 'titleKey'> | null | undefined): string {
    if (!user) return ''
    if (user.titleKey)
        return (
            messages.value.rewards.titles[user.titleKey as keyof typeof messages.value.rewards.titles] ?? t('role.user')
        )
    if (user.role === 'admin') return t('role.admin')
    if (user.role === 'moderator') return t('role.moderator')
    return user.isPremium ? t('role.premium') : t('role.user')
}
