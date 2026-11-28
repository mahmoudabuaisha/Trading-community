/** Profile pictures that ship with the store; they move gently wherever they are shown. */
export const isLivePortrait = (src?: string | null): boolean => !!src && src.startsWith('/images/avatars/')
