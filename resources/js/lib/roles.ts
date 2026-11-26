import { t } from '../i18n'
import { TIERS, type User } from '../types/domain'

type Standing = Pick<User, 'role' | 'isPremium'> & Partial<Pick<User, 'premiumTier'>>

/** A membership name: a preset tier in the reader's language, or the admin's own words. */
export function tierName(tier: string | null | undefined): string {
    if (!tier) return t('role.premium')
    return (TIERS as readonly string[]).includes(tier) ? t(`tiers.${tier}.name`) : tier
}

/** The member's standing: staff role, membership name while subscribed, or plain member. */
export function standingName(user: Standing | null | undefined): string {
    if (!user) return ''
    if (user.role === 'admin') return t('role.admin')
    if (user.role === 'moderator') return t('role.moderator')
    return user.isPremium ? tierName(user.premiumTier) : t('role.user')
}

/** The label shown under a member's name: the title they wear from the store, otherwise their standing. */
export function roleName(user: (Standing & Pick<User, 'title'>) | null | undefined): string {
    if (!user) return ''
    return user.title || standingName(user)
}
